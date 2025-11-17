<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Services\EmailVerificationService;

class EmailVerificationController extends Controller
{
    public function __construct(private EmailVerificationService $service) {}

    public function send(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
        ]);

        $code = $this->service->generateVerificationCode($data['email']);
        $ok = $this->service->sendVerificationCode($data['email'], $code, $request->input('name'));

        Log::info('Email verification code sent', [
            'email' => $data['email'],
            'code' => $code,
            'success' => $ok
        ]);

        return $ok
            ? response()->json(['status'=>true])
            : response()->json(['status'=>false,'message'=>'Failed to send email'], 500);
    }

    public function verify(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'code'  => 'required|string|size:6',
        ]);

        $isValid = $this->service->verifyCode($data['email'], $data['code']);

        Log::info('Email verification code verification', [
            'email' => $data['email'],
            'code' => $data['code'],
            'is_valid' => $isValid
        ]);

        return $isValid
            ? response()->json(['status'=>true])
            : response()->json(['status'=>false,'message'=>'Invalid or expired code'], 422);
    }
}


