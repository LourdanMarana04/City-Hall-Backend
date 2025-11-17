<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Admin;
use App\Models\Citizen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    /**
     * Login user and create token
     */
    public function login(Request $request)
    {
        Log::info('Login attempt', [
            'email' => $request->email,
            'ip' => $request->ip()
        ]);

        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            Log::warning('Login validation failed', [
                'email' => $request->email,
                'errors' => $validator->errors()
            ]);
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Try to find an admin first, then a citizen
        $user = Admin::where('email', $request->email)->first();
        $userType = 'admin';
        if (!$user) {
            $user = Citizen::where('email', $request->email)->first();
            $userType = $user ? 'citizen' : null;
        }

        if (!$user) {
            Log::warning('Login failed - user not found', [
                'email' => $request->email
            ]);
            return response()->json([
                'status' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        if (!Hash::check($request->password, $user->password)) {
            Log::warning('Login failed - invalid password', [
                'email' => $request->email,
                'user_id' => $user->id
            ]);
            return response()->json([
                'status' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        // Only admins have active flag
        if ($user instanceof Admin && !$user->is_active) {
            return response()->json([
                'status' => false,
                'message' => 'Account is deactivated'
            ], 401);
        }

        // Update last login time
        if ($user instanceof Admin) {
            $user->update(['last_login_at' => now()]);
        }

        // Create token
        $token = $user->createToken('auth-token')->plainTextToken;

        $role = $user instanceof Admin ? $user->role : 'user';
        $departmentName = null;
        if ($user instanceof Admin && $user->department_id) {
            $departmentName = optional(\App\Models\Department::find($user->department_id))->name;
        }

        return response()->json([
            'status' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $role,
                    'department' => $departmentName,
                    'department_id' => $user instanceof Admin ? $user->department_id : null,
                    'position' => $user instanceof Admin ? $user->position : null,
                    'phone' => $user instanceof Citizen ? $user->phone_number : null,
                    'address' => $user instanceof Citizen ? $user->address : null,
                ],
                'token' => $token,
                'token_type' => 'Bearer'
            ]
        ], 200);
    }

    /**
     * Logout user (revoke token)
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => true,
            'message' => 'Successfully logged out'
        ], 200);
    }

    /**
     * Get authenticated user profile
     */
    public function profile(Request $request)
    {
        $user = $request->user();

        $role = $user instanceof Admin ? $user->role : 'user';
        $departmentName = null;
        if ($user instanceof Admin && $user->department_id) {
            $departmentName = optional(\App\Models\Department::find($user->department_id))->name;
        }

        return response()->json([
            'status' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $role,
                'department' => $departmentName,
                'department_id' => $user instanceof Admin ? $user->department_id : null,
                'position' => $user instanceof Admin ? $user->position : null,
                'last_login_at' => $user instanceof Admin ? $user->last_login_at : null,
                'phone' => $user instanceof Citizen ? $user->phone_number : null,
                'address' => $user instanceof Citizen ? $user->address : null,
            ]
        ], 200);
    }

    /**
     * Update authenticated user profile
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $uniqueTable = $user instanceof Admin ? 'admins' : 'citizens';
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:' . $uniqueTable . ',email,' . $user->id,
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            Log::warning('Admin/user registration validation failed', [
                'by_user_id' => $authUser ? $authUser->id : null,
                'by_user_role' => $authUser instanceof Admin ? $authUser->role : 'public',
                'payload_role' => $request->role,
                'errors' => $validator->errors(),
            ]);
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Build update array
        $updateData = [
            'name' => $request->name,
            'email' => $request->email,
        ];

        // Add phone and address only for citizens
        if ($user instanceof Citizen) {
            if ($request->has('phone')) {
                $updateData['phone_number'] = $request->phone;
            }
            if ($request->has('address')) {
                $updateData['address'] = $request->address;
            }
        }

        // Update user profile
        $user->update($updateData);

        // Log the profile update
        Log::info('User profile updated', [
            'user_id' => $user->id,
            'user_role' => $user instanceof Admin ? $user->role : 'user',
            'action' => 'update_profile',
            'updated_fields' => array_keys($updateData),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Profile updated successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user instanceof Admin ? $user->role : 'user',
                'department' => ($user instanceof Admin && $user->department_id) ? optional(\App\Models\Department::find($user->department_id))->name : null,
                'department_id' => $user instanceof Admin ? $user->department_id : null,
                'position' => $user instanceof Admin ? $user->position : null,
                'phone' => $user instanceof Citizen ? $user->phone_number : null,
                'address' => $user instanceof Citizen ? $user->address : null,
            ]
        ], 200);
    }

    /**
     * Register new user (for admin use)
     */
    public function register(Request $request)
    {
        $authUser = $request->user();
        $isPublic = !$authUser;

        // Build rules dynamically based on role
        $baseRules = [
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                // Allow special characters while enforcing at least one uppercase and one digit
                'regex:/^(?=.*[A-Z])(?=.*\d).+$/',
            ],
            'role' => 'required|in:admin,super_admin,user',
            'department' => 'nullable|string|max:255',
            'position' => 'nullable|string|max:255',
        ];

        // Determine unique email table and domain restriction for admin roles
        $role = $request->input('role');
        if ($role === 'admin' || $role === 'super_admin') {
            $baseRules['email'][] = Rule::unique('admins', 'email');
            // Restrict to @cityhall.com domain
            $baseRules['email'][] = 'regex:/^[A-Za-z0-9._%+-]+@cityhall\\.com$/i';
        } else {
            $baseRules['email'][] = Rule::unique('citizens', 'email');
        }

        $validator = Validator::make($request->all(), $baseRules, [
            'password.regex' => 'Password must be at least 8 characters long, contain at least one capital letter and one number.',
            'email.regex' => 'For admin accounts, the email must be a @cityhall.com address.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Only superadmins can create admin/super_admin accounts
        if ($isPublic && $request->role !== 'user') {
            return response()->json(['status' => false, 'message' => 'Only superadmins can create admin or superadmin accounts.'], 403);
        }

        if (!$isPublic && ($request->role === 'admin' || $request->role === 'super_admin') && !($authUser instanceof Admin && $authUser->role === 'super_admin')) {
            return response()->json(['status' => false, 'message' => 'Only superadmins can create admin or superadmin accounts.'], 403);
        }

        if ($request->role === 'user') {
            // Create citizen
            $citizen = Citizen::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);
            $created = $citizen;
        } else {
            // Create admin
            // Resolve department_id from name if provided
            $departmentId = null;
            if (!empty($request->department)) {
                $department = \App\Models\Department::whereRaw('LOWER(TRIM(name)) = ?', [strtolower(trim($request->department))])->first();
                if ($department) {
                    $departmentId = $department->id;
                }
            }

            $admin = Admin::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => $request->role,
                'department_id' => $departmentId,
                'position' => $request->position,
                'is_active' => true,
            ]);
            $created = $admin;
        }

        Log::info('User registered', [
            'by_user_id' => $authUser ? $authUser->id : null,
            'by_user_role' => $authUser instanceof Admin ? $authUser->role : 'public',
            'target_user_id' => $created->id,
            'target_user_role' => $created instanceof Admin ? $created->role : 'user',
            'action' => 'register',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'User created successfully',
            'data' => [
                'id' => $created->id,
                'name' => $created->name,
                'email' => $created->email,
                'role' => $created instanceof Admin ? $created->role : 'user',
                'department' => $created instanceof Admin && $created->department_id ? optional(\App\Models\Department::find($created->department_id))->name : null,
                'position' => $created instanceof Admin ? $created->position : null,
            ]
        ], 201);
    }

    /**
     * Register new public user (for web kiosk signup)
     */
    public function registerUser(Request $request)
    {
        Log::info('User registration attempt', [
            'email' => $request->email,
            'firstName' => $request->firstName,
            'lastName' => $request->lastName,
            'ip' => $request->ip()
        ]);

        $validator = Validator::make($request->all(), [
            'firstName' => 'required|string|max:255',
            'lastName' => 'required|string|max:255',
            'birthDate' => 'required|date',
            'gender' => 'nullable|in:male,female,other',
            'email' => 'required|string|email|max:255|unique:citizens',
            'mobileNumber' => 'required|string|max:20',
            'phoneNumber' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'isResident' => 'boolean',
            'password' => [
                'required',
                'string',
                'min:8',
                // Allow special characters while enforcing at least one uppercase and one digit
                'regex:/^(?=.*[A-Z])(?=.*\d).+$/',
            ],
            'confirmPassword' => 'required|same:password',
            'isSeniorCitizen' => 'boolean',
        ], [
            'password.regex' => 'Password must be at least 8 characters long, contain at least one capital letter and one number.',
        ]);

        if ($validator->fails()) {
            Log::warning('User registration validation failed', [
                'email' => $request->email,
                'errors' => $validator->errors()
            ]);
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Create citizen with all the signup form fields
        try {
            $user = Citizen::create([
                'name' => $request->firstName . ' ' . $request->lastName,
                'first_name' => $request->firstName,
                'last_name' => $request->lastName,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'birth_date' => $request->birthDate,
                'gender' => $request->gender,
                'mobile_number' => $request->mobileNumber,
                'phone_number' => $request->phoneNumber,
                'address' => $request->address,
                'is_resident' => $request->boolean('isResident', true),
                'is_senior_citizen' => $request->boolean('isSeniorCitizen', false),
            ]);

            Log::info('User created successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'name' => $user->name
            ]);
        } catch (\Exception $e) {
            Log::error('User creation failed', [
                'email' => $request->email,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }

        Log::info('Public user registered', [
            'user_id' => $user->id,
            'email' => $user->email,
            'is_senior_citizen' => $user->is_senior_citizen,
            'action' => 'public_register',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Registration successful! You can now login with your new account.',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_senior_citizen' => $user->is_senior_citizen,
            ]
        ], 201);
    }

    /**
     * Send password change authentication code to user's email
     */
    public function sendPasswordChangeCode(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        $emailVerificationService = app(\App\Services\EmailVerificationService::class);

        try {
            $code = $emailVerificationService->generatePasswordChangeCode($user->email);
            $sent = $emailVerificationService->sendPasswordChangeCode($user->email, $code, $user->name);

            if ($sent) {
                Log::info('Password change code sent', [
                    'user_id' => $user->id,
                    'email' => $user->email
                ]);

                return response()->json([
                    'status' => true,
                    'message' => 'Authentication code sent to your email successfully'
                ], 200);
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'Failed to send authentication code. Please try again.'
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send password change code', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'An error occurred while sending the authentication code'
            ], 500);
        }
    }

    /**
     * Change user password with email authentication code
     */
    public function changePassword(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/^(?=.*[A-Z])(?=.*\d).+$/',
            ],
            'new_password_confirmation' => 'required|string',
            'auth_code' => 'required|string|size:6',
        ], [
            'new_password.regex' => 'Password must be at least 8 characters long, contain at least one capital letter and one number.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Verify current password
        if (!Hash::check($request->current_password, $user->password)) {
            Log::warning('Password change failed - invalid current password', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Current password is incorrect'
            ], 422);
        }

        // Verify authentication code
        $emailVerificationService = app(\App\Services\EmailVerificationService::class);
        $isCodeValid = $emailVerificationService->verifyPasswordChangeCode($user->email, $request->auth_code);

        if (!$isCodeValid) {
            Log::warning('Password change failed - invalid authentication code', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Invalid or expired authentication code'
            ], 422);
        }

        // Check if new password is different from current password
        if (Hash::check($request->new_password, $user->password)) {
            return response()->json([
                'status' => false,
                'message' => 'New password must be different from your current password'
            ], 422);
        }

        // Update password
        $user->password = Hash::make($request->new_password);
        $user->save();

        // Consume the authentication code after successful password change
        $emailVerificationService->consumePasswordChangeCode($user->email);

        Log::info('Password changed successfully', [
            'user_id' => $user->id,
            'email' => $user->email
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Password changed successfully'
        ], 200);
    }
}
