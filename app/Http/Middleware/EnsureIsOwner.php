<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIsOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->membership()?->isOwner()) {
            return response()->json([
                'message' => 'Only the business owner can do this.',
            ], 403);
        }

        return $next($request);
    }
}
