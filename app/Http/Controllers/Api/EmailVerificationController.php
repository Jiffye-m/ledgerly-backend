<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\VerifyEmailRequest;
use App\Http\Resources\UserResource;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    public function __construct(private OtpService $otpService)
    {
    }

    public function verify(VerifyEmailRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->email_verified_at) {
            return response()->json([
                'message' => 'Email already verified.',
                'user' => new UserResource($user->load(['business.setting', 'business.subscription'])),
            ]);
        }

        $result = $this->otpService->verify($user, $request->otp);

        if (! $result['success']) {
            return response()->json($result, 422);
        }

        return response()->json([
            'message' => $result['message'],
            'user' => new UserResource($user->fresh()->load(['business.setting', 'business.subscription'])),
        ]);
    }

    public function resend(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->email_verified_at) {
            return response()->json(['message' => 'Email already verified.'], 422);
        }

        $seconds = $this->otpService->secondsUntilResendAllowed($user);

        if ($seconds > 0) {
            return response()->json([
                'message' => "Please wait {$seconds}s before requesting another code.",
                'retry_after' => $seconds,
            ], 429);
        }

        $this->otpService->issue($user);

        return response()->json([
            'message' => "A new verification code was sent to {$this->otpService->maskEmail($user->email)}.",
        ]);
    }
}
