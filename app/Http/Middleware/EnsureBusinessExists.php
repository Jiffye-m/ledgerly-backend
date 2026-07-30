<?php

namespace App\Http\Middleware;

use App\Models\BusinessMember;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves which business this request is operating in — a user can now
 * belong to several, so "your business" is no longer a fixed column on
 * the user, it's whatever the client says via X-Business-Id, verified
 * against that user's actual membership on every single request.
 *
 * Kept the original class name/alias ('has.business') so routes/api.php
 * and bootstrap/app.php need zero changes even though what this does
 * changed completely.
 */
class EnsureBusinessExists
{
    public function handle(Request $request, Closure $next): Response
    {
        $businessId = $request->header('X-Business-Id');

        if (! $businessId) {
            return response()->json([
                'message' => 'No business selected. Include an X-Business-Id header.',
                'code' => 'NO_BUSINESS_SELECTED',
            ], 422);
        }

        $membership = BusinessMember::with('business.subscription')
            ->where('business_id', $businessId)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $membership || $membership->status !== 'active') {
            return response()->json([
                'message' => 'You do not have access to this business.',
                'code' => 'NOT_A_MEMBER',
            ], 403);
        }

        $request->attributes->set('business', $membership->business);
        $request->attributes->set('membership', $membership);

        return $next($request);
    }
}
