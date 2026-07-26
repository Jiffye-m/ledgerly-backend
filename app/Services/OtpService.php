<?php

namespace App\Services;

use App\Mail\OtpMail;
use App\Models\EmailOtp;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class OtpService
{
    private const EXPIRY_MINUTES = 10;

    private const RESEND_COOLDOWN_SECONDS = 60;

    private const MAX_ATTEMPTS = 5;

    /**
     * Invalidates any previous unverified code for this user and sends a
     * fresh one. Called on register, and again on every resend — a resend
     * always issues a brand new code rather than re-sending the old one.
     */
    public function issue(User $user): void
    {
        EmailOtp::where('user_id', $user->id)->whereNull('verified_at')->delete();

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        EmailOtp::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'otp' => $code, // hashed automatically via the model's 'hashed' cast
            'expires_at' => now()->addMinutes(self::EXPIRY_MINUTES),
            'attempts' => 0,
        ]);

        Mail::to($user->email)->send(new OtpMail($user->name, $code));
    }

    public function secondsUntilResendAllowed(User $user): int
    {
        $latest = EmailOtp::where('user_id', $user->id)->latest()->first();

        if (! $latest) {
            return 0;
        }

        $elapsed = now()->diffInSeconds($latest->created_at);

        return max(0, self::RESEND_COOLDOWN_SECONDS - $elapsed);
    }

    /**
     * @return array{success: bool, message: string, remaining_attempts?: int}
     */
    public function verify(User $user, string $code): array
    {
        $otp = EmailOtp::where('user_id', $user->id)->whereNull('verified_at')->latest()->first();

        if (! $otp) {
            return ['success' => false, 'message' => 'No verification code found. Please request a new one.'];
        }

        if ($otp->isExpired()) {
            return ['success' => false, 'message' => 'This code has expired. Please request a new one.'];
        }

        if ($otp->hasExceededAttempts()) {
            return ['success' => false, 'message' => 'Too many incorrect attempts. Please request a new code.'];
        }

        if (! Hash::check($code, $otp->otp)) {
            $otp->increment('attempts');
            $remaining = max(0, self::MAX_ATTEMPTS - $otp->attempts);

            return [
                'success' => false,
                'message' => $remaining > 0
                    ? "Incorrect code. {$remaining} attempt".($remaining === 1 ? '' : 's')." remaining."
                    : 'Too many incorrect attempts. Please request a new code.',
                'remaining_attempts' => $remaining,
            ];
        }

        $otp->update(['verified_at' => now()]);
        $user->email_verified_at = now();
        $user->save();

        return ['success' => true, 'message' => 'Email verified.'];
    }

    public function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $visible = substr($local, 0, 3);

        return "{$visible}****@{$domain}";
    }
}
