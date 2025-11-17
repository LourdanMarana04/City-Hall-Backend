<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\QueueController;
use App\Http\Controllers\HRFormController;
use App\Http\Controllers\SeniorCitizenController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\SystemChangeController;
use App\Http\Controllers\NotificationController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Public routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register/user', [AuthController::class, 'registerUser']); // Public user registration
Route::get('/departments', [DepartmentController::class, 'index']);
Route::get('/departments/{id}', [DepartmentController::class, 'show']);

// Email Verification routes
Route::post('/email/send-verification', [App\Http\Controllers\EmailVerificationController::class, 'send']);
Route::post('/email/verify-code', [App\Http\Controllers\EmailVerificationController::class, 'verify']);
Route::post('/email/register', [App\Http\Controllers\EmailRegistrationController::class, 'register']);
// Keep duplicate and register endpoints but move off SMS wording if needed later
Route::post('/sms/check-duplicate', [App\Http\Controllers\SmsVerificationController::class, 'checkDuplicateUser']);
Route::post('/sms/register', [App\Http\Controllers\SmsVerificationController::class, 'registerWithSmsVerification']);

// Test endpoint for debugging
Route::get('/test-login/{email}', function($email) {
    $user = \App\Models\Admin::where('email', $email)->first();
    if (!$user) {
        $user = \App\Models\Citizen::where('email', $email)->first();
    }
    if ($user) {
        return response()->json([
            'found' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user instanceof \App\Models\Admin ? $user->role : 'user',
                'is_active' => $user instanceof \App\Models\Admin ? $user->is_active : true
            ]
        ]);
    } else {
        return response()->json([
            'found' => false,
            'message' => 'User not found'
        ]);
    }
});

// Debug email verification endpoint
Route::get('/debug-email-verification/{email}', function($email) {
    $cacheKey = 'email_verification_' . strtolower($email);
    $cachedCode = \Illuminate\Support\Facades\Cache::get($cacheKey);

    return response()->json([
        'email' => $email,
        'cache_key' => $cacheKey,
        'cached_code' => $cachedCode,
        'cache_exists' => $cachedCode !== null
    ]);
});

// Test email verification endpoint
Route::post('/test-email-verification', function(\Illuminate\Http\Request $request) {
    $email = $request->input('email');
    $code = $request->input('code');

    $service = new \App\Services\EmailVerificationService();

    // Generate a code
    $generatedCode = $service->generateVerificationCode($email);

    // Check if it's stored
    $cacheKey = 'email_verification_' . strtolower($email);
    $storedCode = \Illuminate\Support\Facades\Cache::get($cacheKey);

    // Test verification
    $isValid = $service->verifyCode($email, $code);

    return response()->json([
        'email' => $email,
        'generated_code' => $generatedCode,
        'stored_code' => $storedCode,
        'test_code' => $code,
        'is_valid' => $isValid,
        'cache_key' => $cacheKey
    ]);
});

// Set verification code manually for testing
Route::post('/set-verification-code', function(\Illuminate\Http\Request $request) {
    $email = $request->input('email');
    $code = $request->input('code');

    $cacheKey = 'email_verification_' . strtolower($email);
    \Illuminate\Support\Facades\Cache::put($cacheKey, $code, 600);

    return response()->json([
        'email' => $email,
        'code' => $code,
        'cache_key' => $cacheKey,
        'message' => 'Verification code set successfully'
    ]);
});

// Queue management routes
Route::post('/queue/generate', [QueueController::class, 'generateQueueNumber']);
Route::get('/queue/status/{departmentId}', [QueueController::class, 'getQueueStatus']);
Route::post('/queue/update-status', [QueueController::class, 'updateQueueStatus']);
Route::post('/queue/accept', [QueueController::class, 'acceptQueue']);
Route::post('/queue/cancel', [QueueController::class, 'cancelQueue']);
Route::post('/queue/complete', [QueueController::class, 'completeQueue']);
Route::get('/queue/latest-updates', [QueueController::class, 'getLatestQueueUpdates']);
Route::get('/queue/latest-update/{departmentId}', [QueueController::class, 'getLatestQueueUpdate']);
Route::get('/queue/number/{id}', [QueueController::class, 'getQueueNumberById']);
Route::post('/queue/validate-confirmation-code', [QueueController::class, 'validateConfirmationCode']);
Route::post('/queue/confirm-at-kiosk', [QueueController::class, 'confirmQueueAtKiosk']);
Route::post('/queue/cleanup-expired-codes', [QueueController::class, 'cleanupExpiredConfirmationCodes']);

// System change notifications (public polling)
Route::get('/system-changes', [SystemChangeController::class, 'index']);

// Transaction history (public for viewing)
Route::get('/queue/history/{departmentId}', [QueueController::class, 'getTransactionHistory']);



// Senior citizen verification routes
Route::post('/senior-citizen/verify', [SeniorCitizenController::class, 'verifySeniorCitizen']);
Route::get('/senior-citizen/stats', [SeniorCitizenController::class, 'getSeniorCitizenStats']);

// Set and get currently serving queue number
Route::post('/queue/currently-serving', [\App\Http\Controllers\QueueController::class, 'setCurrentlyServing']);
Route::get('/queue/currently-serving', [\App\Http\Controllers\QueueController::class, 'getCurrentlyServingAll']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::put('/profile/update', [AuthController::class, 'updateProfile']);
    Route::get('/user/profile', [AuthController::class, 'profile']);
    Route::put('/user/profile', [AuthController::class, 'updateProfile']);
    Route::post('/user/send-password-change-code', [AuthController::class, 'sendPasswordChangeCode']);
    Route::post('/user/change-password', [AuthController::class, 'changePassword']);
    Route::post('/register', [AuthController::class, 'register']); // Admin creation (super admin only)
    Route::get('/user', function (Request $request) {
        $user = $request->user();
        // If citizen, map phone_number to phone
        if ($user instanceof \App\Models\Citizen) {
            return response()->json([
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone_number,
                'address' => $user->address,
                'phone_number' => $user->phone_number,
            ]);
        }
        return $user;
    });
    Route::get('/analytics', [AnalyticsController::class, 'query']); // Kept for backward compatibility if needed
    Route::get('/analytics/queue-metrics', [AnalyticsController::class, 'queueMetrics']);
    Route::get('/analytics/wait-time-metrics', [AnalyticsController::class, 'waitTimeMetrics']);
    Route::get('/analytics/department-metrics', [AnalyticsController::class, 'departmentMetrics']);
    Route::get('/analytics/kiosk-usage-metrics', [AnalyticsController::class, 'kioskUsageMetrics']);
    Route::get('/analytics/historical', [AnalyticsController::class, 'historical']);
    Route::get('/reports/superadmin', [AnalyticsController::class, 'superAdminReports']);
    Route::get('/reports/department', [AnalyticsController::class, 'departmentReports']);
    Route::get('/reports/department/canceled', [AnalyticsController::class, 'departmentCanceledTransactions']);
    Route::get('/reports/cancellation-reasons', [AnalyticsController::class, 'getCancellationReasons']);
    Route::apiResource('departments', DepartmentController::class)->except(['index', 'show']);
    Route::post('departments/{department}/transactions', [DepartmentController::class, 'storeTransaction']);

    // Protected queue management routes (admin only)
    Route::post('/queue/accept', [QueueController::class, 'acceptTransaction']);
    Route::post('/queue/cancel', [QueueController::class, 'cancelTransaction']);
    Route::post('/queue/reset/{departmentId}', [QueueController::class, 'resetQueue']);
    Route::get('/queue/today-completed/{departmentId}', [QueueController::class, 'getTodayCompletedTransactions']);
    Route::post('/queue/clear-all-serving', [QueueController::class, 'clearAllCurrentlyServing']);
    Route::post('/queue/update-details', [QueueController::class, 'updateQueueDetails']);
    Route::put('departments/{department}/transactions/{transaction}', [DepartmentController::class, 'updateTransaction']);
    Route::apiResource('users', App\Http\Controllers\UserController::class)->except(['store']);
    Route::post('/hr/checklist-pdf', [HRFormController::class, 'generateChecklistPdf']);
    Route::get('/superadmin/overview', [QueueController::class, 'superAdminOverview']);
    Route::get('/queue/user-status', [QueueController::class, 'getUserQueueStatus']);
    Route::post('/system-changes', [SystemChangeController::class, 'store']);

    // Backup and Restore routes (Super Admin only - enforced in controller)
    Route::prefix('backup')->group(function () {
        Route::post('/create', [BackupController::class, 'createBackup']);
        Route::get('/list', [BackupController::class, 'listBackups']);
        Route::get('/download/{id}', [BackupController::class, 'downloadBackup']);
        Route::post('/restore/{id}', [BackupController::class, 'restoreBackup']);
        Route::delete('/delete/{id}', [BackupController::class, 'deleteBackup']);
    });

    // Notification routes
    Route::prefix('notifications')->group(function () {
        Route::get('/unread', [NotificationController::class, 'getUnreadNotifications']);
        Route::get('/all', [NotificationController::class, 'getAllNotifications']);
        Route::get('/created', [NotificationController::class, 'getCreatedNotifications']);
        Route::post('/mark-read', [NotificationController::class, 'markAsRead']);
        Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead']);
        Route::post('/create', [NotificationController::class, 'createNotification']);
    });
});
