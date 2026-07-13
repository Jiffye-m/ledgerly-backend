<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBusinessExists
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()->business_id) {
            return response()->json([
                'message' => 'Create your business first.',
                'code' => 'NO_BUSINESS',
            ], 422);
        }

        return $next($request);
    }
}
