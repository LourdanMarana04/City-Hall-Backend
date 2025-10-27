<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use App\Services\EmailVerificationService;
use App\Models\Citizen;

class EmailRegistrationController extends Controller
{
    public function __construct(private EmailVerificationService $service) {}

    public function register(Request $request)
    {
        Log::info('Email registration attempt', [
            'request_data' => $request->all()
        ]);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:citizens,email',
            'phone_number' => 'required|string|max:20|unique:citizens,phone_number',
            'birthday' => 'required|date',
            'address' => 'nullable|string|max:500',
            'verification_code' => 'required|string|size:6',
            'password' => 'required|string|min:8|regex:/^(?=.*[A-Z])(?=.*\d)[A-Za-z\d]+$/',
        ], [
            'password.regex' => 'Password must be at least 8 characters long, contain at least one capital letter and one number.',
        ]);

        if ($validator->fails()) {
            Log::warning('Email registration validation failed', [
                'request_data' => $request->all(),
                'errors' => $validator->errors()
            ]);
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Verify email code
        if (!$this->service->verifyCode($request->email, $request->verification_code)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid or expired verification code'
            ], 422);
        }

        try {
            $user = Citizen::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'phone_number' => $request->phone_number,
                'birthday' => $request->birthday,
                'address' => $request->address,
                'is_verified' => true,
            ]);

            // Consume the verification code only after successful registration
            $this->service->consumeVerificationCode($request->email);

            Log::info('User registered with EMAIL verification', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'User registered successfully',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone_number' => $user->phone_number,
                        'is_verified' => $user->is_verified,
                    ]
                ]
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Email registration error', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => false,
                'message' => 'Registration failed. Please try again.'
            ], 500);
        }
    }
}


