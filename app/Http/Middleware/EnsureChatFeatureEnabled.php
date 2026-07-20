<?php

namespace App\Http\Middleware;

use App\Services\ChatSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureChatFeatureEnabled
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (! ChatSettings::enabled($feature)) {
            abort(403, 'This chat feature is not currently available.');
        }

        return $next($request);
    }
}
