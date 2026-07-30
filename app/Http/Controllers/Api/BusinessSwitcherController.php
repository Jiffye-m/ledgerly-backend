<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MembershipResource;
use App\Models\BusinessMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BusinessSwitcherController extends Controller
{
    /**
     * GET /my/businesses — every business this user can operate in, in any
     * role, deliberately outside the has.business-gated section: you need
     * this list *before* you can pick an X-Business-Id to send on
     * everything else. This is what the frontend's business switcher
     * (and the post-login "which business?" screen) is built on.
     */
    public function index(Request $request): JsonResponse
    {
        $memberships = BusinessMember::with(['business.subscription', 'branch'])
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->get();

        return response()->json([
            'memberships' => MembershipResource::collection($memberships),
        ]);
    }
}
