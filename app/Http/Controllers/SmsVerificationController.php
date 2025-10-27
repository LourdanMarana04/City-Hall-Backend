<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\InfobipSmsService;
use App\Models\Citizen;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class SmsVerificationController extends Controller
{
    protected $smsService;

    public function __construct(InfobipSmsService $smsService)
    {
        $this->smsService = $smsService;
    }

    /**
     * Send SMS verification code
     */
    public function sendVerificationCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|string|regex:/^\+?[1-9]\d{1,14}$/',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid phone number format',
                'errors' => $validator->errors()
            ], 422);
        }

        $phoneNumber = $request->phone_number;

        // Check if phone number is already registered
        $existingUser = Citizen::where('phone_number', $phoneNumber)->first();
        if ($existingUser) {
            return response()->json([
                'status' => false,
                'message' => 'Phone number is already registered'
            ], 409);
        }

        try {
            $code = $this->smsService->generateVerificationCode($phoneNumber);
            // Dispatch async job to send SMS so the API returns immediately
            \App\Jobs\SendOtpSmsJob::dispatch($phoneNumber, $code)->onQueue('sms');

                return response()->json([
                    'status' => true,
                'message' => 'Verification code queued for sending'
                ], 200);
        } catch (\Exception $e) {
            Log::error('SMS verification error', [
                'phone' => $phoneNumber,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'An error occurred while sending verification code'
            ], 500);
        }
    }

    /**
     * Verify SMS code
     */
    public function verifyCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|string',
            'code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid verification code format',
                'errors' => $validator->errors()
            ], 422);
        }

        $phoneNumber = $request->phone_number;
        $code = $request->code;

        $isValid = $this->smsService->verifyCode($phoneNumber, $code);

        if ($isValid) {
            return response()->json([
                'status' => true,
                'message' => 'Phone number verified successfully'
            ], 200);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Invalid or expired verification code'
            ], 400);
        }
    }

    /**
     * Check for duplicate users before registration
     */
    public function checkDuplicateUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone_number' => 'required|string',
            'birthday' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $name = $request->name;
        $email = $request->email;
        $phoneNumber = $request->phone_number;
        $birthday = $request->birthday;

        // Check for exact duplicates
        $exactDuplicate = Citizen::where('name', $name)
            ->where('email', $email)
            ->where('phone_number', $phoneNumber)
            ->where('birthday', $birthday)
            ->first();

        if ($exactDuplicate) {
            return response()->json([
                'status' => false,
                'message' => 'An account with these exact details already exists',
                'duplicate_type' => 'exact',
                'existing_user' => [
                    'id' => $exactDuplicate->id,
                    'name' => $exactDuplicate->name,
                    'email' => $exactDuplicate->email,
                    'phone_number' => $exactDuplicate->phone_number,
                    'created_at' => $exactDuplicate->created_at
                ]
            ], 409);
        }

        // Check for potential duplicates (same name but different contact info)
        $potentialDuplicates = Citizen::where('name', $name)
            ->where(function($query) use ($email, $phoneNumber) {
                $query->where('email', '!=', $email)
                      ->orWhere('phone_number', '!=', $phoneNumber);
            })
            ->get();

        if ($potentialDuplicates->count() > 0) {
            return response()->json([
                'status' => false,
                'message' => 'Found potential duplicate accounts with the same name but different contact information',
                'duplicate_type' => 'potential',
                'potential_duplicates' => $potentialDuplicates->map(function($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone_number' => $user->phone_number,
                        'created_at' => $user->created_at
                    ];
                })
            ], 409);
        }

        return response()->json([
            'status' => true,
            'message' => 'No duplicates found, registration can proceed'
        ], 200);
    }

    /**
     * Register user with SMS verification
     */
    public function registerWithSmsVerification(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:citizens,email',
            'phone_number' => 'required|string|unique:citizens,phone_number',
            'birthday' => 'required|date',
            'address' => 'nullable|string|max:500',
            'verification_code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Verify SMS code first
        $isCodeValid = $this->smsService->verifyCode($request->phone_number, $request->verification_code);

        if (!$isCodeValid) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid or expired verification code'
            ], 400);
        }

        // Check for duplicates one more time
        $duplicateCheck = $this->checkDuplicateUser($request);
        $duplicateData = json_decode($duplicateCheck->getContent(), true);

        if (!$duplicateData['status']) {
            return response()->json($duplicateData, 409);
        }

        try {
            $user = Citizen::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone_number' => $request->phone_number,
                'birthday' => $request->birthday,
                'address' => $request->address,
                'is_verified' => true, // SMS verified
            ]);

            Log::info('User registered with SMS verification', [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone_number' => $user->phone_number
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
                        'is_verified' => $user->is_verified
                    ]
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('User registration error', [
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Registration failed. Please try again.'
            ], 500);
        }
    }
}


