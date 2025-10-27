<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailVerificationService
{
    public function generateVerificationCode(string $email): string
    {
        $cacheKey = $this->cacheKey($email);

        // Check if a code already exists and is still valid
        $existingCode = Cache::get($cacheKey);
        if ($existingCode) {
            Log::info('Using existing verification code', [
                'email' => $email,
                'existing_code' => $existingCode,
                'cache_key' => $cacheKey
            ]);
            return $existingCode;
        }

        // Generate new code only if none exists
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Log::info('Generating new verification code', [
            'email' => $email,
            'code' => $code,
            'cache_key' => $cacheKey
        ]);

        $stored = Cache::put($cacheKey, $code, 600);

        Log::info('Cache storage result', [
            'cache_key' => $cacheKey,
            'stored' => $stored,
            'retrieved' => Cache::get($cacheKey)
        ]);

        return $code;
    }

    public function sendVerificationCode(string $email, string $code, ?string $name = null): bool
    {
        try {
            if (config('mail.default')) {
                $logoUrl = url('storage/logo-seal.png');
                Mail::send('emails.verification', ['code' => $code, 'name' => $name, 'logoUrl' => $logoUrl], function ($m) use ($email) {
                    $m->to($email)->subject('Your Verification Code for Cabuyao Cityhall Web Kios');
                });
                return true;
            }
            // Fallback to log in case mail isn't configured
            Log::info('Mock email sent', ['email' => $email, 'code' => $code]);
            return true;
        } catch (\Throwable $e) {
            Log::error('Email verification send error', ['email' => $email, 'error' => $e->getMessage()]);
            return false;
        }
    }

    public function verifyCode(string $email, string $code): bool
    {
        $stored = Cache::get($this->cacheKey($email));
        if ($stored && $stored === $code) {
            // Don't delete the code immediately - let it expire naturally
            // This allows multiple verification attempts if needed
            return true;
        }
        return false;
    }

    public function consumeVerificationCode(string $email): void
    {
        // Only delete the code when registration is actually completed
        Cache::forget($this->cacheKey($email));
    }

    private function cacheKey(string $email): string
    {
        return 'email_verification_'.strtolower($email);
    }
}


