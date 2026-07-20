<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatMessage extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['user_id', 'body', 'role_snapshot', 'edited_at'];

    protected $casts = ['edited_at' => 'datetime'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isEdited(): bool
    {
        return $this->edited_at !== null;
    }

    public function toClientArray(): array
    {
        return [
            'id'         => $this->id,
            'body'       => $this->body,
            'user_id'    => $this->user_id,
            'author'     => $this->author?->name,
            'role'       => $this->role_snapshot,
            'edited'     => $this->isEdited(),
            'deleted'    => $this->trashed(),
            'created_at' => $this->created_at?->toIso8601String(),
            'edited_at'  => $this->edited_at?->toIso8601String(),
        ];
    }
}
