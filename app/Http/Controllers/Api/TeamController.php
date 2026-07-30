<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\StoreTeamMemberRequest;
use App\Http\Requests\Team\UpdateTeamMemberRequest;
use App\Http\Resources\TeamMemberResource;
use App\Models\BusinessMember;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $members = BusinessMember::with(['user', 'branch'])
            ->where('business_id', $request->business()->id)
            ->orderByRaw("FIELD(role, 'owner', 'admin', 'staff')")
            ->get();

        return response()->json([
            'team' => TeamMemberResource::collection($members),
        ]);
    }

    /**
     * If the email already belongs to a Ledgerly account (they run their
     * own separate business, say, or are staff elsewhere), this attaches
     * that existing account to this business instead of creating a
     * duplicate — a person is one account across every business they
     * touch, never several.
     */
    public function store(StoreTeamMemberRequest $request): JsonResponse
    {
        $businessId = $request->business()->id;

        $user = User::where('email', $request->email)->first();

        if ($user && BusinessMember::where('business_id', $businessId)->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'This person is already a member of this business.'], 422);
        }

        if (! $user) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => $request->password, // hashed automatically via the 'hashed' cast
                'is_active' => true,
            ]);
        }

        $member = BusinessMember::create([
            'business_id' => $businessId,
            'user_id' => $user->id,
            'role' => $request->role,
            'branch_id' => $request->branch_id,
            'status' => 'active',
        ]);

        return response()->json([
            'team_member' => new TeamMemberResource($member->load(['user', 'branch'])),
        ], 201);
    }

    public function update(UpdateTeamMemberRequest $request, BusinessMember $member): JsonResponse
    {
        $this->authorizeBusiness($member);

        if ($member->isOwner()) {
            return response()->json(['message' => 'The owner\'s role can\'t be changed here.'], 403);
        }

        $member->update($request->validated());

        return response()->json([
            'team_member' => new TeamMemberResource($member->load(['user', 'branch'])),
        ]);
    }

    /**
     * Deactivates this one membership — not the user account itself,
     * which may still be active on other businesses they belong to.
     * Sales/expenses this person logged stay attributed to them; only
     * their access to *this* business is revoked.
     */
    public function destroy(BusinessMember $member): JsonResponse
    {
        $this->authorizeBusiness($member);

        if ($member->isOwner()) {
            return response()->json(['message' => 'The owner can\'t be removed.'], 403);
        }

        $member->update(['status' => 'deactivated']);

        return response()->json(['message' => 'Team member removed from this business.']);
    }
}
