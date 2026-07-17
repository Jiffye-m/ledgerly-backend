<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\StoreTeamMemberRequest;
use App\Http\Requests\Team\UpdateTeamMemberRequest;
use App\Http\Resources\TeamMemberResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $members = User::where('business_id', $request->user()->business_id)
            ->orderByRaw("FIELD(role, 'owner', 'admin', 'staff')")
            ->orderBy('name')
            ->get();

        return response()->json([
            'team' => TeamMemberResource::collection($members),
        ]);
    }

    public function store(StoreTeamMemberRequest $request): JsonResponse
    {
        $member = User::create([
            'business_id' => $request->user()->business_id,
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => $request->password, // hashed automatically via the 'hashed' cast
            'role' => $request->role,
            'is_active' => true,
        ]);

        return response()->json([
            'team_member' => new TeamMemberResource($member),
        ], 201);
    }

    public function update(UpdateTeamMemberRequest $request, User $member): JsonResponse
    {
        $this->authorizeBusiness($member);

        if ($member->role === 'owner') {
            return response()->json(['message' => 'The owner\'s role can\'t be changed here.'], 403);
        }

        $member->update($request->validated());

        return response()->json([
            'team_member' => new TeamMemberResource($member),
        ]);
    }

    /**
     * Deactivates rather than deletes — see the note in EnsureNotStaff /
     * the migrations: user_id on sales and expenses cascade-deletes, so
     * removing the row would wipe that person's entire sales history.
     * Deactivating just blocks login while keeping every record intact.
     */
    public function destroy(User $member): JsonResponse
    {
        $this->authorizeBusiness($member);

        if ($member->role === 'owner') {
            return response()->json(['message' => 'The owner can\'t be removed.'], 403);
        }

        $member->update(['is_active' => false]);
        $member->tokens()->delete(); // immediately invalidate any active sessions

        return response()->json(['message' => 'Team member deactivated.']);
    }
}
