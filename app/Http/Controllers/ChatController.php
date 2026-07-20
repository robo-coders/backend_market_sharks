<?php

namespace App\Http\Controllers;

use App\Events\MessageDeleted;
use App\Events\MessageSent;
use App\Events\MessageUpdated;
use App\Events\ReadReceipt;
use App\Http\Requests\StoreMessageRequest;
use App\Http\Requests\UpdateMessageRequest;
use App\Models\ChatMessage;
use App\Services\ChatSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    private const PER_PAGE = 50;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ChatMessage::class);

        $messages = ChatMessage::with('author')
            ->latest('id')
            ->cursorPaginate(self::PER_PAGE);

        return response()->json([
            'data'        => collect($messages->items())
                ->map(fn (ChatMessage $m) => $m->toClientArray())
                ->values(),
            'next_cursor' => optional($messages->nextCursor())->encode(),
            'features'    => ChatSettings::all(),
        ]);
    }

    public function store(StoreMessageRequest $request): JsonResponse
    {
        $user = $request->user();

        $message = ChatMessage::create([
            'user_id'       => $user->id,
            'body'          => $request->validated('body'),
            'role_snapshot' => $user->getRoleNames()->first(),
        ]);

        $message->setRelation('author', $user);

        broadcast(new MessageSent($message))->toOthers();

        return response()->json(['message' => $message->toClientArray()], 201);
    }

    public function update(UpdateMessageRequest $request, ChatMessage $message): JsonResponse
    {
        $message->update([
            'body'      => $request->validated('body'),
            'edited_at' => now(),
        ]);

        $message->load('author');

        broadcast(new MessageUpdated($message))->toOthers();

        return response()->json(['message' => $message->toClientArray()]);
    }

    public function destroy(ChatMessage $message): JsonResponse
    {
        $this->authorize('delete', $message);

        $id = $message->id;
        $message->delete();

        broadcast(new MessageDeleted($id))->toOthers();

        return response()->json(['id' => $id]);
    }

    public function markRead(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->authorize('viewAny', ChatMessage::class);

        $user->forceFill(['chat_last_read_at' => now()])->save();

        if (ChatSettings::enabled('read_receipts')) {
            broadcast(new ReadReceipt(
                userId: $user->id,
                readAt: $user->chat_last_read_at->toIso8601String(),
            ))->toOthers();
        }

        return response()->json(['read_at' => $user->chat_last_read_at?->toIso8601String()]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->authorize('viewAny', ChatMessage::class);

        $count = ChatMessage::when(
            $user->chat_last_read_at,
            fn ($q) => $q->where('created_at', '>', $user->chat_last_read_at)
        )->count();

        return response()->json(['unread' => $count]);
    }
}
