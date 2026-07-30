<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNotStaff
{
    /**
     * Staff can still sell, add customers, log expenses, etc. — they just
     * can't delete records or void a sale. Deleting/voiding erases or
     * reverses something that already happened, which is exactly the kind
     * of action a cashier shouldn't be able to do unsupervised.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->membership()?->isStaff()) {
            return response()->json([
                'message' => 'Staff accounts can\'t do this — ask an admin or the owner.',
            ], 403);
        }

        return $next($request);
    }
}
