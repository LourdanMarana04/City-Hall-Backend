<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class InfobipSmsService
{
    private string $baseUrl;
    private string $apiKey;
    private string $sender;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.infobip.base_url', ''), '/');
        $this->apiKey  = (string) config('services.infobip.api_key', '');
        $this->sender  = (string) config('services.infobip.sender', config('app.name', 'CityHall'));
    }

    private function toE164(string $phone): string
    {
        $d = preg_replace('/\D+/', '', $phone);
        if (str_starts_with($d, '09') && strlen($d) >= 11) return '+63'.substr($d, 1);
        if (str_starts_with($d, '63')) return '+'.$d;
        if (str_starts_with($phone, '+')) return $phone;
        return '+'.$d;
    }

    public function sendVerificationCode(string $phoneNumber, string $code): bool
    {
        if (empty($this->baseUrl) || empty($this->apiKey)) {
            Log::warning('Infobip not configured - using mock send');
            return $this->mockSmsSend($phoneNumber, $code);
        }

        $payload = [
            'messages' => [[
                'from'         => $this->sender,
                'destinations' => [[ 'to' => $this->toE164($phoneNumber) ]],
                'text'         => "Your City Hall verification code is: {$code}. This code expires in 10 minutes.",
            ]],
        ];

        $http = Http::withHeaders([
                'Authorization' => 'App '.$this->apiKey,
                'Accept'        => 'application/json',
            ])
            ->connectTimeout(5)
            ->timeout(10)
            ->retry(2, 200);

        // Optional: trust custom CA bundle or disable verify for local debugging
        $ca = config('services.infobip.ca_bundle');
        if ($ca) {
            $http = $http->withOptions(['verify' => $ca]);
        } else if (!config('services.infobip.ssl_verify', true)) {
            $http = $http->withOptions(['verify' => false]);
        }

        $resp = $http->baseUrl($this->baseUrl)->post('/sms/2/text/advanced', $payload);

        Log::info('infobip_sms_resp', ['status' => $resp->status(), 'body' => $resp->json()]);
        if (!$resp->successful()) {
            $msg = optional($resp->json())['requestError']['serviceException']['text'] ?? $resp->body();
            Log::error('Infobip SMS failed', ['phone' => $phoneNumber, 'status' => $resp->status(), 'error' => $msg]);
            return false;
        }
        return true;
    }

    public function generateVerificationCode(string $phoneNumber): string
    {
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $cacheKey = "sms_verification_{$phoneNumber}";
        Cache::put($cacheKey, $code, 600);
        return $code;
    }

    public function verifyCode(string $phoneNumber, string $code): bool
    {
        $cacheKey = "sms_verification_{$phoneNumber}";
        $storedCode = Cache::get($cacheKey);
        if ($storedCode && $storedCode === $code) {
            Cache::forget($cacheKey);
            return true;
        }
        return false;
    }

    private function mockSmsSend(string $phoneNumber, string $code): bool
    {
        Log::info('Mock SMS sent (Infobip)', [
            'phone' => $phoneNumber,
            'code'  => $code,
        ]);
        if (config('app.debug')) {
            file_put_contents(
                storage_path('logs/mock_sms_codes.txt'),
                "Phone: {$phoneNumber}, Code: {$code}, Time: ".now()."\n",
                FILE_APPEND
            );
        }
        return true;
    }
}


