<?php

namespace App\Services;

use Vonage\Client;
use Vonage\Client\Credentials\Basic;
use Vonage\SMS\Message\SMS;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class SmsService
{
    protected $client;

    public function __construct()
    {
        $apiKey = config('services.vonage.key');
        $apiSecret = config('services.vonage.secret');

        if ($apiKey && $apiSecret) {
            $this->client = new Client(new Basic($apiKey, $apiSecret));
        }
    }

    /**
     * Send SMS verification code
     */
    public function sendVerificationCode($phoneNumber, $code)
    {
        try {
            if (!$this->client) {
                Log::warning('SMS service not configured - using mock mode');
                return $this->mockSmsSend($phoneNumber, $code);
            }

            $message = "Your City Hall verification code is: {$code}. This code expires in 10 minutes.";

            $response = $this->client->sms()->send(
                new SMS($phoneNumber, config('app.name', 'City Hall'), $message)
            );

            $message = $response->current();

            if ($message->getStatus() == 0) {
                Log::info('SMS sent successfully', [
                    'phone' => $phoneNumber,
                    'message_id' => $message->getMessageId()
                ]);
                return true;
            } else {
                Log::error('SMS failed to send', [
                    'phone' => $phoneNumber,
                    'status' => $message->getStatus(),
                    'error' => $message->getErrorText()
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('SMS service error', [
                'phone' => $phoneNumber,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Generate and store verification code
     */
    public function generateVerificationCode($phoneNumber)
    {
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $cacheKey = "sms_verification_{$phoneNumber}";

        // Store code for 10 minutes
        Cache::put($cacheKey, $code, 600);

        return $code;
    }

    /**
     * Verify SMS code
     */
    public function verifyCode($phoneNumber, $code)
    {
        $cacheKey = "sms_verification_{$phoneNumber}";
        $storedCode = Cache::get($cacheKey);

        if ($storedCode && $storedCode === $code) {
            // Remove code after successful verification
            Cache::forget($cacheKey);
            return true;
        }

        return false;
    }

    /**
     * Mock SMS sending for development/testing
     */
    private function mockSmsSend($phoneNumber, $code)
    {
        Log::info('Mock SMS sent', [
            'phone' => $phoneNumber,
            'code' => $code,
            'message' => 'This is a mock SMS for development'
        ]);

        // In development, you might want to store the code in a file or database
        // for easy testing
        if (config('app.debug')) {
            file_put_contents(
                storage_path('logs/mock_sms_codes.txt'),
                "Phone: {$phoneNumber}, Code: {$code}, Time: " . now() . "\n",
                FILE_APPEND
            );
        }

        return true;
    }
}


