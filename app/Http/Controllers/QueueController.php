<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;

class QueueController extends Controller
{
    /**
     * Generate a new queue number for a department and transaction
     */
    public function generateQueueNumber(Request $request)
    {
        try {
            $request->validate([
                'department_id' => 'required|exists:departments,id',
                'transaction_id' => 'required|exists:transactions,id',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Queue generation validation failed', [
                'errors' => $e->errors(),
                'request_data' => $request->all()
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid request data',
                'errors' => $e->errors()
            ], 422);
        }

        try {
            $department = Department::findOrFail($request->department_id);
            $transaction = Transaction::findOrFail($request->transaction_id);
        } catch (\Exception $e) {
            Log::error('Department or Transaction not found', [
                'department_id' => $request->department_id,
                'transaction_id' => $request->transaction_id,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Department or transaction not found'
            ], 404);
        }

        // Get department prefix using new naming convention
        $prefix = $this->getDepartmentPrefix($department->name);

        // Get current date for daily reset
        $today = Carbon::today()->format('Y-m-d');

        // Get the highest queue number for today (all statuses) to ensure continuous numbering
        $lastQueue = DB::table('queue_numbers')
            ->where('department_id', $department->id)
            ->whereDate('created_at', $today)
            ->orderBy('queue_number', 'desc')
            ->first();

        // Generate new queue number
        $nextNumber = $lastQueue ? intval($lastQueue->queue_number) + 1 : 1;
        $queueNumber = $prefix . '#' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

        // Store the queue number
        $priority = $request->boolean('priority', false);
        $source = $request->input('source', 'kiosk'); // Default to 'kiosk' if not provided
        $userId = auth('sanctum')->check() ? auth('sanctum')->id() : null;
        $isSeniorCitizen = $request->boolean('senior_citizen', false);

        // Check if user is authenticated and get their senior citizen status from database
        $citizenId = null;
        if ($userId) {
            // Check if the authenticated user is a citizen
            $citizen = \App\Models\Citizen::find($userId);
            if ($citizen) {
                $citizenId = $citizen->id;
                if ($citizen->is_senior_citizen) {
                    $isSeniorCitizen = true;
                }
            }
            // If not a citizen, check if it's an admin (but don't set citizen_id)
            $admin = \App\Models\Admin::find($userId);
            if (!$citizen && !$admin) {
                // User ID doesn't exist in either table, set to null to avoid foreign key constraint
                $userId = null;
            }
        }

        // Senior citizens automatically get priority
        if ($isSeniorCitizen) {
            $priority = true;
        }

        // Generate confirmation code for web users
        $confirmationCode = null;
        $status = 'waiting';

        if ($source === 'web') {
            $status = 'pending_confirmation';
            $confirmationCode = $this->generateConfirmationCode();
        }

        // Only set user_id if it exists in the users table to avoid foreign key constraint violation
        $validUserId = null;
        if ($userId) {
            $userExists = DB::table('users')->where('id', $userId)->exists();
            if ($userExists) {
                $validUserId = $userId;
            }
        }

        try {
            $queueId = DB::table('queue_numbers')->insertGetId([
                'department_id' => $department->id,
                'transaction_id' => $transaction->id,
                'queue_number' => $nextNumber,
                'full_queue_number' => $queueNumber,
                'status' => $status,
                'priority' => $priority,
                'source' => $source,
                'confirmation_code' => $confirmationCode,
                'user_id' => $validUserId, // Only set if user exists in users table
                'citizen_id' => $citizenId,
                'senior_citizen' => $isSeniorCitizen,
                // Accept persisted details if provided
                'citizen_name' => $request->input('citizen_name'),
                'property_address' => $request->input('property_address'),
                'assessment_value' => $request->input('assessment_value'),
                'tax_amount' => $request->input('tax_amount'),
                'assigned_staff' => $request->input('assigned_staff'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to insert queue number', [
                'error' => $e->getMessage(),
                'department_id' => $department->id,
                'transaction_id' => $transaction->id,
                'queue_number' => $queueNumber,
                'user_id' => $validUserId,
                'citizen_id' => $citizenId
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to generate queue number. Please try again.'
            ], 500);
        }

        // Log successful queue generation
        Log::info('Queue number generated successfully', [
            'queue_id' => $queueId,
            'queue_number' => $queueNumber,
            'department' => $department->name,
            'transaction' => $transaction->name,
            'source' => $source,
            'user_id' => $validUserId,
            'citizen_id' => $citizenId
        ]);

        // Calculate estimated wait time
        $estimatedWaitTime = $this->calculateEstimatedWaitTime($department->id, $transaction->id);

        $response = [
            'queue_id' => $queueId,
            'queue_number' => $queueNumber,
            'department' => $department,
            'transaction' => $transaction,
            'estimated_wait_time' => $estimatedWaitTime,
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'priority' => $priority,
            'source' => $source,
            'senior_citizen' => $isSeniorCitizen,
        ];

        // Add confirmation code to response for web users
        if ($source === 'web') {
            $response['confirmation_code'] = $confirmationCode;
            $response['status'] = 'pending_confirmation';
        }

        // Broadcast the queue update for real-time display (only for confirmed numbers)
        if ($status === 'waiting') {
            $this->broadcastQueueUpdate($department->id, $queueNumber, $transaction->name, $priority);
        }

        return response()->json($response);
    }

    /**
     * Generate a unique confirmation code
     */
    private function generateConfirmationCode()
    {
        do {
            $code = '';
            $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            for ($i = 0; $i < 6; $i++) {
                $code .= $characters[rand(0, strlen($characters) - 1)];
            }

            // Check if code already exists
            $exists = DB::table('queue_numbers')->where('confirmation_code', $code)->exists();
        } while ($exists);

        return $code;
    }

    /**
     * Broadcast queue update for real-time display
     */
    private function broadcastQueueUpdate($departmentId, $queueNumber, $transactionName, $priority = false)
    {
        // For now, we'll use a simple approach with a custom endpoint
        // In a production environment, you might want to use Laravel Echo or Pusher
        $queueData = [
            'department_id' => $departmentId,
            'queue_number' => $queueNumber,
            'transaction_name' => $transactionName,
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'status' => 'waiting',
            'priority' => $priority,
        ];

        // Store the latest queue update in cache for real-time access
        cache()->put("queue_update_{$departmentId}", $queueData, 300); // 5 minutes
    }

    /**
     * Calculate estimated wait time based on current queue length and average processing time
     */
    private function calculateEstimatedWaitTime($departmentId, $transactionId)
    {
        // Get current queue length (waiting status)
        $queueLength = DB::table('queue_numbers')
            ->where('department_id', $departmentId)
            ->where('transaction_id', $transactionId)
            ->where('status', 'waiting')
            ->whereDate('created_at', Carbon::today())
            ->count();

        // Get average processing time from recent completed transactions (last 7 days)
        $avgProcessingTime = DB::table('queue_numbers')
            ->where('department_id', $departmentId)
            ->where('transaction_id', $transactionId)
            ->where('status', 'completed')
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->whereNotNull('completed_at')
            ->avg(DB::raw('TIMESTAMPDIFF(MINUTE, created_at, completed_at)'));

        // Default average processing time if no data available (10 minutes)
        $avgProcessingTime = $avgProcessingTime ?: 10;

        // Calculate estimated wait time
        $estimatedMinutes = $queueLength * $avgProcessingTime;

        return [
            'minutes' => $estimatedMinutes,
            'formatted' => $this->formatWaitTime($estimatedMinutes),
        ];
    }

    /**
     * Format wait time in a human-readable format
     */
    private function formatWaitTime($minutes)
    {
        if ($minutes < 60) {
            return round($minutes) . ' minutes';
        } else {
            $hours = floor($minutes / 60);
            $remainingMinutes = round($minutes % 60);
            if ($remainingMinutes === 0) {
                return $hours . ' hour' . ($hours > 1 ? 's' : '');
            } else {
                return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ' . $remainingMinutes . ' minutes';
            }
        }
    }

    /**
     * Get all queue numbers for a department
     */
    public function getQueueStatus($departmentId)
    {
        $user = request()->user();
        $department = \App\Models\Department::findOrFail($departmentId);

        // Only check department access if user is authenticated and is an admin
        if ($user instanceof \App\Models\Admin && $user->role === 'admin') {
            if ($user->department_id !== $department->id) {
                return response()->json(['error' => 'Forbidden'], 403);
            }
        }

        $queueNumbers = DB::table('queue_numbers')
            ->join('transactions', 'queue_numbers.transaction_id', '=', 'transactions.id')
            ->select('queue_numbers.*', 'transactions.name as transaction_name')
            ->where('queue_numbers.department_id', $departmentId)
            ->whereDate('queue_numbers.created_at', Carbon::today())
            ->whereIn('queue_numbers.status', ['waiting', 'pending'])
            ->where('queue_numbers.status', '!=', 'pending_confirmation') // Exclude unconfirmed web numbers
            ->orderBy('queue_numbers.queue_number', 'asc')
            ->get();



        return response()->json($queueNumbers);
    }

    /**
     * Get latest queue updates for real-time display
     */
    public function getLatestQueueUpdates()
    {
        $departments = Department::all();
        $latestUpdates = [];

        foreach ($departments as $department) {
            $cacheKey = "queue_update_{$department->id}";
            $latestUpdate = cache()->get($cacheKey);

            if ($latestUpdate) {
                $latestUpdates[] = $latestUpdate;
            }
        }

        return response()->json($latestUpdates);
    }

    /**
     * Get latest queue update for a specific department
     */
    public function getLatestQueueUpdate($departmentId)
    {
        $cacheKey = "queue_update_{$departmentId}";
        $latestUpdate = cache()->get($cacheKey);

        return response()->json($latestUpdate ?: null);
    }

    /**
     * Mark queue number as completed
     */
    public function completeQueue(Request $request)
    {
        $request->validate([
            'queue_id' => 'required|exists:queue_numbers,id',
        ]);

        // Get the queue details before updating
        $queue = DB::table('queue_numbers')
            ->where('id', $request->queue_id)
            ->first();

        if (!$queue) {
            return response()->json(['error' => 'Queue not found'], 404);
        }

        // Update the queue status and compute duration
        DB::table('queue_numbers')
            ->where('id', $request->queue_id)
            ->update([
                'status' => 'completed',
                'completed_at' => now(),
                'duration_minutes' => DB::raw('TIMESTAMPDIFF(MINUTE, COALESCE(started_at, created_at), NOW())'),
                'updated_at' => now(),
            ]);

        // Clear the "Now Serving" cache for this department when queue is completed
        cache()->forget("currently_serving_{$queue->department_id}");

        return response()->json(['message' => 'Queue completed successfully']);
    }

    /**
     * Update queue status (successful/failed)
     */
    public function updateQueueStatus(Request $request)
    {
        $request->validate([
            'queue_id' => 'required|exists:queue_numbers,id',
            'status' => 'required|in:successful,failed',
        ]);

        // Get the queue details before updating
        $queue = DB::table('queue_numbers')
            ->where('id', $request->queue_id)
            ->first();

        if (!$queue) {
            return response()->json(['error' => 'Queue not found'], 404);
        }

        // Update the queue status and compute duration
        $updateData = [
            'status' => $request->status,
            'completed_at' => now(),
            'duration_minutes' => DB::raw('TIMESTAMPDIFF(MINUTE, COALESCE(started_at, created_at), NOW())'),
            'updated_at' => now(),
        ];

        // Add cancel reason if provided and status is failed
        if ($request->status === 'failed' && $request->has('cancel_reason')) {
            $updateData['cancel_reason'] = $request->cancel_reason;
        }

        DB::table('queue_numbers')
            ->where('id', $request->queue_id)
            ->update($updateData);

        // Clear the "Now Serving" cache for this department when queue is completed
        if (in_array($request->status, ['successful', 'failed'])) {
            cache()->forget("currently_serving_{$queue->department_id}");
        }

        return response()->json(['message' => 'Queue status updated successfully']);
    }

    /**
     * Update persisted reporting details for a queue (idempotent)
     */
    public function updateQueueDetails(Request $request)
    {
        $request->validate([
            'queue_id' => 'required|exists:queue_numbers,id',
        ]);

        $data = [];
        foreach (['citizen_name', 'property_address', 'assessment_value', 'tax_amount', 'assigned_staff'] as $field) {
            if ($request->filled($field)) {
                $data[$field] = $request->input($field);
            }
        }
        if (empty($data)) {
            return response()->json(['message' => 'No fields to update'], 200);
        }

        DB::table('queue_numbers')->where('id', $request->queue_id)->update(array_merge($data, [
            'updated_at' => now(),
        ]));

        return response()->json(['message' => 'Queue details updated']);
    }

    /**
     * Accept a transaction (mark as pending for processing)
     */
    public function acceptTransaction(Request $request)
    {
        $request->validate([
            'queue_id' => 'required|exists:queue_numbers,id',
        ]);

        $user = request()->user();
        $queue = DB::table('queue_numbers')
            ->join('departments', 'queue_numbers.department_id', '=', 'departments.id')
            ->select('queue_numbers.*', 'departments.name as department_name')
            ->where('queue_numbers.id', $request->queue_id)
            ->first();

        if (!$queue) {
            return response()->json(['error' => 'Queue not found'], 404);
        }

        // Check if user has access to this department
        if ($user instanceof \App\Models\Admin && $user->role === 'admin') {
            if ($user->department_id !== $queue->department_id) {
                return response()->json(['error' => 'Forbidden'], 403);
            }
        }

        // Update the queue status to pending (accepted for processing) and set started_at if not already set
        DB::table('queue_numbers')
            ->where('id', $request->queue_id)
            ->update([
                'status' => 'pending',
                'started_at' => DB::raw('COALESCE(started_at, NOW())'),
                'updated_at' => now(),
            ]);

        // Set as currently serving
        cache()->put("currently_serving_{$queue->department_id}", $queue->full_queue_number, 3600);

        return response()->json([
            'message' => 'Transaction accepted successfully',
            'queue_number' => $queue->full_queue_number
        ]);
    }

    /**
     * Cancel a transaction (mark as failed)
     */
    public function cancelTransaction(Request $request)
    {
        $request->validate([
            'queue_id' => 'required|exists:queue_numbers,id',
            'cancel_reason' => 'nullable|string',
        ]);

        $user = request()->user();
        $queue = DB::table('queue_numbers')
            ->join('departments', 'queue_numbers.department_id', '=', 'departments.id')
            ->select('queue_numbers.*', 'departments.name as department_name')
            ->where('queue_numbers.id', $request->queue_id)
            ->first();

        if (!$queue) {
            return response()->json(['error' => 'Queue not found'], 404);
        }

        // Check if user has access to this department
        if ($user instanceof \App\Models\Admin && $user->role === 'admin') {
            if ($user->department_id !== $queue->department_id) {
                return response()->json(['error' => 'Forbidden'], 403);
            }
        }

        // Update the queue status to failed and compute duration
        $updateData = [
            'status' => 'failed',
            'completed_at' => now(),
            'duration_minutes' => DB::raw('TIMESTAMPDIFF(MINUTE, COALESCE(started_at, created_at), NOW())'),
            'updated_at' => now(),
        ];

        // Add cancel reason if provided
        if ($request->has('cancel_reason')) {
            $updateData['cancel_reason'] = $request->cancel_reason;
        }

        DB::table('queue_numbers')
            ->where('id', $request->queue_id)
            ->update($updateData);

        // Clear the currently serving cache if this was the currently serving queue
        $currentlyServing = cache()->get("currently_serving_{$queue->department_id}");
        if ($currentlyServing === $queue->full_queue_number) {
            cache()->forget("currently_serving_{$queue->department_id}");
        }

        return response()->json([
            'message' => 'Transaction canceled successfully',
            'queue_number' => $queue->full_queue_number
        ]);
    }

    /**
     * Reset all queue numbers for a department
     */
    public function resetQueue($departmentId)
    {
        $user = request()->user();
        $department = \App\Models\Department::findOrFail($departmentId);

        // Only check department access if user is authenticated and is an admin
        if ($user instanceof \App\Models\Admin && $user->role === 'admin') {
            if ($user->department_id !== $department->id) {
                return response()->json(['error' => 'Forbidden'], 403);
            }
        }

        try {
            // Delete ALL today's queue numbers for this department to reset completely
            $deletedCount = DB::table('queue_numbers')
                ->where('department_id', $departmentId)
                ->whereDate('created_at', Carbon::today())
                ->delete();

            // Clear the currently serving cache for this department
            cache()->forget("currently_serving_{$departmentId}");

            Log::info('Queue reset successfully', [
                'department_id' => $departmentId,
                'deleted_count' => $deletedCount,
                'date' => Carbon::today()->format('Y-m-d')
            ]);

            return response()->json([
                'message' => 'Queue has been reset successfully.',
                'deleted_count' => $deletedCount
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to reset queue', [
                'department_id' => $departmentId,
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => 'Failed to reset queue'], 500);
        }
    }

    /**
     * Clear all currently serving data (useful for server restart)
     */
    public function clearAllCurrentlyServing()
    {
        $departments = Department::all();
        foreach ($departments as $department) {
            cache()->forget("currently_serving_{$department->id}");
        }
        return response()->json(['message' => 'All currently serving data cleared successfully.']);
    }

    /**
     * Get transaction history (successful/failed) for a department
     */
    public function getTransactionHistory($departmentId)
    {
        $user = request()->user();

        try {
            $department = \App\Models\Department::findOrFail($departmentId);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Department not found'], 404);
        }

        // Only check department access if user is authenticated and is an admin
        if ($user instanceof \App\Models\Admin && $user->role === 'admin') {
            if ($user->department_id !== $department->id) {
                return response()->json(['error' => 'Forbidden'], 403);
            }
        }
        try {
            $history = DB::table('queue_numbers')
                ->join('transactions', 'queue_numbers.transaction_id', '=', 'transactions.id')
                ->join('departments', 'queue_numbers.department_id', '=', 'departments.id')
                ->select(
                    'queue_numbers.id',
                    'queue_numbers.queue_number',
                    'queue_numbers.full_queue_number',
                    'queue_numbers.status',
                    'queue_numbers.cancel_reason',
                    'queue_numbers.completed_at',
                    'queue_numbers.started_at',
                    'queue_numbers.duration_minutes',
                    'queue_numbers.source',
                    'queue_numbers.citizen_name',
                    'queue_numbers.property_address',
                    'queue_numbers.assessment_value',
                    'queue_numbers.tax_amount',
                    'queue_numbers.assigned_staff',
                    'transactions.name as transaction_name',
                    'departments.name as department_name'
                )
                ->where('queue_numbers.department_id', $departmentId)
                // Return all transactions including successful, failed, and cancelled to show complete history
                ->whereIn('queue_numbers.status', ['completed', 'successful', 'failed', 'canceled'])
                ->orderBy('queue_numbers.completed_at', 'desc')
                ->get();

            return response()->json($history);
        } catch (\Exception $e) {
            Log::error('Error in getTransactionHistory: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Get today's completed transactions for dashboard
     */
    public function getTodayCompletedTransactions($departmentId)
    {
        $user = request()->user();

        try {
            $department = \App\Models\Department::findOrFail($departmentId);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Department not found'], 404);
        }

        // Only check department access if user is authenticated and is an admin
        if ($user instanceof \App\Models\Admin && $user->role === 'admin') {
            if ($user->department_id !== $department->id) {
                return response()->json(['error' => 'Forbidden'], 403);
            }
        }

        try {
            $todayCompleted = DB::table('queue_numbers')
                ->join('transactions', 'queue_numbers.transaction_id', '=', 'transactions.id')
                ->join('departments', 'queue_numbers.department_id', '=', 'departments.id')
                ->select(
                    'queue_numbers.id',
                    'queue_numbers.queue_number',
                    'queue_numbers.full_queue_number',
                    'queue_numbers.status',
                    'queue_numbers.completed_at',
                    'queue_numbers.source',
                    'transactions.name as transaction_name',
                    'departments.name as department_name'
                )
                ->where('queue_numbers.department_id', $departmentId)
                ->whereIn('queue_numbers.status', ['successful', 'failed', 'completed'])
                ->whereDate('queue_numbers.completed_at', \Carbon\Carbon::today())
                ->orderBy('queue_numbers.completed_at', 'desc')
                ->get();

            return response()->json($todayCompleted);
        } catch (\Exception $e) {
            Log::error('Error in getTodayCompletedTransactions: ' . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Set the currently serving queue number for a department
     */
    public function setCurrentlyServing(Request $request)
    {
        $user = $request->user();
        $department = \App\Models\Department::findOrFail($request->department_id);
        // Only check department access if user is authenticated and is an admin
        if ($user instanceof \App\Models\Admin && $user->role === 'admin') {
            if ($user->department_id !== $department->id) {
                return response()->json(['error' => 'Forbidden'], 403);
            }
        }
        $request->validate([
            'department_id' => 'required|exists:departments,id',
            'queue_number' => 'required|string',
        ]);
        $departmentId = $request->department_id;
        $queueNumber = $request->queue_number;
        cache()->put("currently_serving_{$departmentId}", $queueNumber, 300); // 5 minutes
        return response()->json(['message' => 'Currently serving number set successfully']);
    }

    /**
     * Get the currently serving queue number for all departments
     */
    public function getCurrentlyServingAll(Request $request)
    {
        $user = $request->user();
        $departments = Department::all();

        // Filter departments based on user role
        if ($user instanceof \App\Models\Admin && $user->role === 'admin' && $user->department_id) {
            $departments = $departments->where('id', $user->department_id);
        }

        $currentlyServing = [];
        foreach ($departments as $department) {
            $queueNumber = cache()->get("currently_serving_{$department->id}");
            $currentlyServing[] = [
                'department_id' => $department->id,
                'department_name' => $department->name,
                'queue_number' => $queueNumber,
            ];
        }
        return response()->json($currentlyServing);
    }

    /**
     * Super Admin Overview Stats
     */
    public function superAdminOverview()
    {
        $totalDepartments = \App\Models\Department::count();
        $ticketsToday = DB::table('queue_numbers')
            ->whereDate('created_at', \Carbon\Carbon::today())
            ->count();
        $pendingTickets = DB::table('queue_numbers')
            ->whereIn('status', ['waiting', 'pending'])
            ->count();
        $successfulTransactions = DB::table('queue_numbers')
            ->where('status', 'completed')
            ->whereDate('completed_at', \Carbon\Carbon::today())
            ->count();
        $failedTransactions = DB::table('queue_numbers')
            ->where('status', 'failed')
            ->whereDate('updated_at', \Carbon\Carbon::today())
            ->count();

        return response()->json([
            'total_departments' => $totalDepartments,
            'tickets_today' => $ticketsToday,
            'pending_tickets' => $pendingTickets,
            'successful_transactions' => $successfulTransactions,
            'failed_transactions' => $failedTransactions,
        ]);
    }

    /**
     * Get the latest queue status for the authenticated user (today only)
     */
    public function getUserQueueStatus(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        $queueNumbers = DB::table('queue_numbers')
            ->join('departments', 'queue_numbers.department_id', '=', 'departments.id')
            ->join('transactions', 'queue_numbers.transaction_id', '=', 'transactions.id')
            ->select(
                'queue_numbers.id',
                'queue_numbers.queue_number',
                'queue_numbers.full_queue_number',
                'queue_numbers.status',
                'queue_numbers.created_at',
                'queue_numbers.completed_at',
                'queue_numbers.source',
                'departments.name as department_name',
                'transactions.name as transaction_name'
            )
            ->where(function($q) use ($user) {
                $q->where('queue_numbers.citizen_id', $user->id)
                  ->orWhere('queue_numbers.user_id', $user->id);
            })
            ->whereDate('queue_numbers.created_at', Carbon::today())
            ->orderBy('queue_numbers.created_at', 'desc')
            ->get();
        return response()->json($queueNumbers);
    }

    /**
     * Validate confirmation code and get queue details
     */
    public function validateConfirmationCode(Request $request)
    {
        $request->validate([
            'confirmation_code' => 'required|string|size:6',
        ]);

        try {
            $queue = DB::table('queue_numbers')
                ->join('transactions', 'queue_numbers.transaction_id', '=', 'transactions.id')
                ->join('departments', 'queue_numbers.department_id', '=', 'departments.id')
                ->select('queue_numbers.*', 'transactions.name as transaction_name', 'departments.name as department_name')
                ->where('queue_numbers.confirmation_code', $request->confirmation_code)
                ->where('queue_numbers.status', 'pending_confirmation')
                ->where('queue_numbers.created_at', '>=', now()->subHour()) // 1 hour expiration
                ->first();

            if (!$queue) {
                return response()->json(['error' => 'Invalid or expired confirmation code'], 404);
            }

            return response()->json([
                'queue_id' => $queue->id,
                'queue_number' => $queue->full_queue_number,
                'transaction_name' => $queue->transaction_name,
                'department_name' => $queue->department_name,
                'priority' => $queue->priority,
                'senior_citizen' => $queue->senior_citizen,
                'created_at' => $queue->created_at,
                'expires_at' => Carbon::parse($queue->created_at)->addHour()->format('Y-m-d H:i:s'),
            ]);
        } catch (\Exception $e) {
            Log::error('Error validating confirmation code: ' . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Confirm queue number at kiosk
     */
    public function confirmQueueAtKiosk(Request $request)
    {
        $request->validate([
            'confirmation_code' => 'required|string|size:6',
        ]);

        try {
            DB::beginTransaction();

            $queue = DB::table('queue_numbers')
                ->where('confirmation_code', $request->confirmation_code)
                ->where('status', 'pending_confirmation')
                ->where('created_at', '>=', now()->subHour()) // 1 hour expiration
                ->lockForUpdate()
                ->first();

            if (!$queue) {
                DB::rollBack();
                return response()->json(['error' => 'Invalid or expired confirmation code'], 404);
            }

            // Update queue status to waiting and set confirmed timestamp
            DB::table('queue_numbers')
                ->where('id', $queue->id)
                ->update([
                    'status' => 'waiting',
                    'confirmed_at' => now(),
                    'updated_at' => now(),
                ]);

            // Get updated queue details for broadcasting
            $updatedQueue = DB::table('queue_numbers')
                ->join('transactions', 'queue_numbers.transaction_id', '=', 'transactions.id')
                ->where('queue_numbers.id', $queue->id)
                ->select('queue_numbers.*', 'transactions.name as transaction_name')
                ->first();

            DB::commit();

            // Broadcast the queue update for real-time display
            $this->broadcastQueueUpdate($queue->department_id, $updatedQueue->full_queue_number, $updatedQueue->transaction_name, $updatedQueue->priority);

            return response()->json([
                'message' => 'Queue number confirmed successfully',
                'queue_number' => $updatedQueue->full_queue_number,
                'status' => 'waiting',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error confirming queue at kiosk: ' . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Clean up expired confirmation codes (can be called by a scheduled task)
     */
    public function cleanupExpiredConfirmationCodes()
    {
        try {
            $expiredCount = DB::table('queue_numbers')
                ->where('status', 'pending_confirmation')
                ->where('created_at', '<', now()->subHour())
                ->update([
                    'status' => 'expired',
                    'updated_at' => now(),
                ]);

            Log::info("Cleaned up {$expiredCount} expired confirmation codes");
            return response()->json(['message' => "Cleaned up {$expiredCount} expired codes"]);
        } catch (\Exception $e) {
            Log::error('Error cleaning up expired confirmation codes: ' . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Get department prefix based on new naming convention
     */
    private function getDepartmentPrefix($departmentName)
    {
        $prefixMap = [
            'Assessor' => 'ASR',
            'Treasury' => 'TRY',
            'Office of the City Mayor' => 'OCM',
            'Office of the City Planning and Development Coordinator' => 'CPDC',
            'City Planning and Development Coordinator' => 'CPDC',
            'Human Resources Management Office' => 'HRM',
            'Sangguniang Panglungsod' => 'SP',
            'City Information Office' => 'CIO',
            'Office of the City Administrator' => 'OCA',
            'General Services Office' => 'GSO',
            'Business Permits and Licensing Office' => 'BPLO',
        ];

        // Check for exact match first
        if (isset($prefixMap[$departmentName])) {
            return $prefixMap[$departmentName];
        }

        // Check for partial matches (case insensitive)
        $departmentNameLower = strtolower($departmentName);
        foreach ($prefixMap as $key => $prefix) {
            if (strpos($departmentNameLower, strtolower($key)) !== false) {
                return $prefix;
            }
        }

        // Fallback to first 3 letters if no match found
        return strtoupper(substr($departmentName, 0, 3));
    }
}
