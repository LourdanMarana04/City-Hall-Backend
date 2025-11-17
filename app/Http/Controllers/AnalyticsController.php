<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class AnalyticsController extends Controller
{
    /**
     * Get real-time queue metrics
     */
    public function queueMetrics(Request $request)
    {
        try {
            $departmentId = $request->get('department_id');
            $timeRange = $request->get('timeRange', '1h');

            // Get current metrics (today's queue status)
            $currentQuery = DB::table('queue_numbers');
            if ($departmentId) {
                $currentQuery->where('department_id', $departmentId);
            }

            $current = $currentQuery->whereDate('created_at', Carbon::today())
                ->selectRaw('
                    COUNT(*) as total_queue,
                    COUNT(CASE WHEN status IN ("completed", "successful") THEN 1 END) as served,
                    COUNT(CASE WHEN status IN ("failed", "canceled") THEN 1 END) as canceled,
                    COUNT(CASE WHEN status = "waiting" THEN 1 END) as waiting,
                    COUNT(CASE WHEN status = "pending" THEN 1 END) as pending
                ')
                ->first();

            // Get trends for last 24 hours (hourly breakdown)
            $trendsQuery = DB::table('queue_numbers');
            if ($departmentId) {
                $trendsQuery->where('department_id', $departmentId);
            }

            $trends = $trendsQuery->where('created_at', '>=', Carbon::now()->subHours(24))
                ->selectRaw('
                    DATE(created_at) as date,
                    HOUR(created_at) as hour,
                    COUNT(*) as total_queue,
                    COUNT(CASE WHEN status IN ("completed", "successful") THEN 1 END) as served,
                    COUNT(CASE WHEN status IN ("failed", "canceled") THEN 1 END) as canceled,
                    COUNT(CASE WHEN status = "waiting" THEN 1 END) as waiting,
                    COUNT(CASE WHEN status = "pending" THEN 1 END) as pending
                ')
                ->groupBy(DB::raw('DATE(created_at)'), DB::raw('HOUR(created_at)'))
                ->orderBy(DB::raw('DATE(created_at)'))
                ->orderBy(DB::raw('HOUR(created_at)'))
                ->get()
                ->map(function($item) {
                    $item->time = $item->date . ' ' . str_pad($item->hour, 2, '0', STR_PAD_LEFT) . ':00:00';
                    return $item;
                });

            return response()->json([
                'status' => 'success',
                'data' => [
                    'current' => $current ?: [
                        'total_queue' => 0,
                        'served' => 0,
                        'canceled' => 0,
                        'waiting' => 0,
                        'pending' => 0
                    ],
                    'trends' => $trends
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching queue metrics: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch queue metrics'
            ], 500);
        }
    }

    /**
     * Get real-time wait time metrics
     */
    public function waitTimeMetrics(Request $request)
    {
        try {
            $departmentId = $request->get('department_id');
            $timeRange = $request->get('timeRange', '1h');

            // Get current metrics (today's wait times)
            $currentQuery = DB::table('queue_numbers')->whereIn('status', ['completed', 'successful']);
            if ($departmentId) {
                $currentQuery->where('department_id', $departmentId);
            }

            $current = $currentQuery->whereDate('created_at', Carbon::today())
                ->selectRaw('
                    AVG(TIMESTAMPDIFF(MINUTE, created_at, COALESCE(completed_at, updated_at))) as average_wait,
                    MAX(TIMESTAMPDIFF(MINUTE, created_at, COALESCE(completed_at, updated_at))) as max_wait,
                    MIN(TIMESTAMPDIFF(MINUTE, created_at, COALESCE(completed_at, updated_at))) as min_wait
                ')
                ->first();

            // Get trends for last 24 hours (hourly breakdown)
            $trendsQuery = DB::table('queue_numbers')->whereIn('status', ['completed', 'successful']);
            if ($departmentId) {
                $trendsQuery->where('department_id', $departmentId);
            }

            $trends = $trendsQuery->where('created_at', '>=', Carbon::now()->subHours(24))
                ->selectRaw('
                    DATE(created_at) as date,
                    HOUR(created_at) as hour,
                    AVG(TIMESTAMPDIFF(MINUTE, created_at, COALESCE(completed_at, updated_at))) as average_wait,
                    MAX(TIMESTAMPDIFF(MINUTE, created_at, COALESCE(completed_at, updated_at))) as max_wait,
                    MIN(TIMESTAMPDIFF(MINUTE, created_at, COALESCE(completed_at, updated_at))) as min_wait
                ')
                ->groupBy(DB::raw('DATE(created_at)'), DB::raw('HOUR(created_at)'))
                ->orderBy(DB::raw('DATE(created_at)'))
                ->orderBy(DB::raw('HOUR(created_at)'))
                ->get()
                ->map(function($item) {
                    $item->time = $item->date . ' ' . str_pad($item->hour, 2, '0', STR_PAD_LEFT) . ':00:00';
                    return $item;
                });

            return response()->json([
                'status' => 'success',
                'data' => [
                    'current' => $current ?: [
                        'average_wait' => 0,
                        'max_wait' => 0,
                        'min_wait' => 0
                    ],
                    'trends' => $trends
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching wait time metrics: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch wait time metrics'
            ], 500);
        }
    }

    /**
     * Get real-time kiosk usage metrics
     */
    public function kioskUsageMetrics(Request $request)
    {
        try {
            $departmentId = $request->get('department_id');

            $query = DB::table('queue_numbers');

            if ($departmentId) {
                $query->where('department_id', $departmentId);
            }

            // Web kiosk usage (source = 'web')
            $webKioskQuery = clone $query;
            $webKiosk = $webKioskQuery->where('source', 'web')
                ->select(
                    DB::raw('COUNT(*) as total_usage'),
                    DB::raw('COUNT(CASE WHEN status = "waiting" THEN 1 END) as current_active'),
                    DB::raw('ROUND((COUNT(CASE WHEN status IN ("completed", "successful") THEN 1 END) / COUNT(*)) * 100, 2) as success_rate')
                )
                ->whereDate('created_at', Carbon::today())
                ->first();

            // Physical kiosk usage (source = 'kiosk')
            $physicalKioskQuery = clone $query;
            $physicalKiosk = $physicalKioskQuery->where('source', 'kiosk')
                ->select(
                    DB::raw('COUNT(*) as total_usage'),
                    DB::raw('COUNT(CASE WHEN status = "waiting" THEN 1 END) as current_active'),
                    DB::raw('ROUND((COUNT(CASE WHEN status IN ("completed", "successful") THEN 1 END) / COUNT(*)) * 100, 2) as success_rate')
                )
                ->whereDate('created_at', Carbon::today())
                ->first();

            // Calculate percentages
            $totalUsage = ($webKiosk->total_usage ?? 0) + ($physicalKiosk->total_usage ?? 0);
            $webPercentage = $totalUsage > 0 ? round(($webKiosk->total_usage ?? 0) / $totalUsage * 100, 2) : 0;
            $physicalPercentage = $totalUsage > 0 ? round(($physicalKiosk->total_usage ?? 0) / $totalUsage * 100, 2) : 0;

            return response()->json([
                'status' => 'success',
                'data' => [
                    'web_kiosk' => $webKiosk ?: [
                        'total_usage' => 0,
                        'current_active' => 0,
                        'success_rate' => 0
                    ],
                    'physical_kiosk' => $physicalKiosk ?: [
                        'total_usage' => 0,
                        'current_active' => 0,
                        'success_rate' => 0
                    ],
                    'comparison' => [
                        'web_percentage' => $webPercentage,
                        'physical_percentage' => $physicalPercentage
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching kiosk usage metrics: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch kiosk usage metrics'
            ], 500);
        }
    }

    /**
     * Get real-time department metrics
     */
    public function departmentMetrics(Request $request)
    {
        try {
            $departmentId = $request->get('department_id');

            $query = DB::table('queue_numbers');

            if ($departmentId) {
                $query->where('department_id', $departmentId);
            }

            $metrics = $query->select(
                DB::raw('COUNT(*) as total_transactions'),
                DB::raw('COUNT(CASE WHEN status IN ("completed", "successful") THEN 1 END) as completed_transactions'),
                DB::raw('COUNT(CASE WHEN status IN ("failed", "canceled") THEN 1 END) as canceled_transactions'),
                DB::raw('COUNT(CASE WHEN status = "failed" THEN 1 END) as failed_transactions'),
                DB::raw('ROUND(AVG(TIMESTAMPDIFF(MINUTE, created_at, COALESCE(completed_at, updated_at))), 2) as average_wait_time'),
                DB::raw('ROUND((COUNT(CASE WHEN status IN ("completed", "successful") THEN 1 END) / COUNT(*)) * 100, 2) as efficiency_rate')
            )
            ->whereDate('created_at', Carbon::today())
            ->first();

            return response()->json([
                'status' => 'success',
                'data' => $metrics ?: [
                    'total_transactions' => 0,
                    'completed_transactions' => 0,
                    'canceled_transactions' => 0,
                    'failed_transactions' => 0,
                    'average_wait_time' => 0,
                    'efficiency_rate' => 0
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching department metrics: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch department metrics'
            ], 500);
        }
    }

    /**
     * Get historical analytics data
     */
    public function historical(Request $request)
    {
        try {
            $departmentId = $request->get('department_id');
            $timePeriod = $request->get('time_period', 'month');
            $selectedTransaction = $request->get('transaction');

            // Calculate date range based on time period
            $dateRanges = [
                'day' => Carbon::now()->subDay(),
                'week' => Carbon::now()->subWeek(),
                'month' => Carbon::now()->subMonth(),
                '6months' => Carbon::now()->subMonths(6),
                'year' => Carbon::now()->subYear()
            ];

            $startDate = $dateRanges[$timePeriod] ?? Carbon::now()->subMonth();

            // Base query
            $baseQuery = DB::table('queue_numbers')
                ->where('created_at', '>=', $startDate);

            if ($departmentId) {
                $baseQuery->where('department_id', $departmentId);
            }

            // Handle transaction filtering
            $transactionId = null;
            if ($selectedTransaction) {
                // If selectedTransaction is an object with id property
                if (is_array($selectedTransaction) && isset($selectedTransaction['id'])) {
                    $transactionId = $selectedTransaction['id'];
                }
                // If selectedTransaction is just an ID
                elseif (is_numeric($selectedTransaction)) {
                    $transactionId = $selectedTransaction;
                }
                // If selectedTransaction is a name, find the ID
                elseif (is_string($selectedTransaction)) {
                    $transaction = DB::table('transactions')
                        ->where('name', $selectedTransaction)
                        ->where('department_id', $departmentId)
                        ->first();
                    if ($transaction) {
                        $transactionId = $transaction->id;
                    }
                }

                if ($transactionId) {
                    $baseQuery->where('transaction_id', $transactionId);
                }
            }

            // Get transactions data
            $transactions = (clone $baseQuery)->selectRaw('
                DATE(created_at) as date,
                COUNT(*) as count
            ')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

            // Get wait times data
            $waitTimes = (clone $baseQuery)->whereIn('status', ['completed', 'successful'])
                ->selectRaw('
                    DATE(COALESCE(completed_at, created_at)) as date,
                    AVG(TIMESTAMPDIFF(MINUTE, created_at, COALESCE(completed_at, updated_at))) as average_wait
                ')
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            // Get canceled transactions data
            $canceledTransactions = (clone $baseQuery)->whereIn('status', ['failed', 'canceled'])
                ->selectRaw('
                    DATE(created_at) as date,
                    COUNT(*) as count
                ')
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            // Get kiosk usage data
            $kioskUsage = $baseQuery->selectRaw('
                COUNT(CASE WHEN source = "web" THEN 1 END) as web,
                COUNT(CASE WHEN source = "kiosk" THEN 1 END) as physical
            ')
            ->first();

            // Average durations (support both persisted and computed if columns not yet migrated)
            $hasDuration = Schema::hasColumn('queue_numbers', 'duration_minutes');
            $hasStarted = Schema::hasColumn('queue_numbers', 'started_at');
            $durationExpr = $hasDuration
                ? 'duration_minutes'
                : 'TIMESTAMPDIFF(MINUTE, COALESCE(started_at, created_at), COALESCE(completed_at, updated_at))';

            $avgCompletedDuration = (clone $baseQuery)
                ->whereIn('status', ['completed', 'successful'])
                ->avg(DB::raw($durationExpr));
            $avgCanceledDuration = (clone $baseQuery)
                ->whereIn('status', ['failed', 'canceled'])
                ->avg(DB::raw($durationExpr));

            // Detailed transaction logs
            $logsQuery = DB::table('queue_numbers')
                ->leftJoin('users', 'queue_numbers.user_id', '=', 'users.id')
                ->leftJoin('transactions', 'queue_numbers.transaction_id', '=', 'transactions.id')
                ->select(
                    'queue_numbers.id as id',
                    'queue_numbers.transaction_id as transaction_id',
                    'transactions.name as transaction_name',
                    DB::raw('COALESCE(queue_numbers.citizen_name, users.name, "Unknown") as user_name'),
                    'queue_numbers.status as status',
                    'queue_numbers.cancel_reason as cancel_reason',
                    DB::raw(($hasDuration ? 'queue_numbers.duration_minutes' : 'TIMESTAMPDIFF(MINUTE, COALESCE(queue_numbers.started_at, queue_numbers.created_at), COALESCE(queue_numbers.completed_at, queue_numbers.updated_at))') . ' as duration_minutes'),
                    'queue_numbers.started_at as started_at',
                    'queue_numbers.completed_at as completed_at',
                    'queue_numbers.created_at as created_at'
                )
                ->where('queue_numbers.created_at', '>=', $startDate);
            if ($departmentId) {
                $logsQuery->where('queue_numbers.department_id', $departmentId);
            }
            if ($transactionId) {
                $logsQuery->where('queue_numbers.transaction_id', $transactionId);
            }
            $transactionLogs = $logsQuery->orderBy('queue_numbers.created_at', 'desc')->limit(500)->get();

            // Get cancellation reasons from actual data
            $cancellationReasons = (clone $baseQuery)->whereIn('status', ['failed', 'canceled'])
                ->selectRaw('
                    CASE
                        WHEN cancel_reason IS NOT NULL THEN cancel_reason
                        WHEN status = "failed" THEN "System Error"
                        WHEN status = "canceled" THEN "User Cancellation"
                        ELSE "Other"
                    END as reason,
                    COUNT(*) as count
                ')
                ->groupBy('reason')
                ->get()
                ->map(function($item) {
                    return [
                        'reason' => $item->reason,
                        'count' => (int)$item->count,
                        'percentage' => 0 // Will be calculated in frontend
                    ];
                })
                ->toArray();

            // If no cancellation data, provide empty array
            if (empty($cancellationReasons)) {
                $cancellationReasons = [];
            }

            // Build per-transaction breakdown
            $perTxBase = DB::table('queue_numbers')
                ->join('transactions', 'queue_numbers.transaction_id', '=', 'transactions.id')
                ->where('queue_numbers.created_at', '>=', $startDate);
            if ($departmentId) {
                $perTxBase->where('queue_numbers.department_id', $departmentId);
            }
            if ($transactionId) {
                $perTxBase->where('queue_numbers.transaction_id', $transactionId);
            }

            // Only run per-transaction queries if no specific transaction is selected
            // This prevents unnecessary complex queries when filtering by a specific transaction
            $txCounts = collect();
            $txWaits = collect();
            $txCanceled = collect();

            if (!$transactionId) {
                // Transactions per transaction
                $txCounts = (clone $perTxBase)
                    ->selectRaw('transactions.name as transaction_name, DATE(queue_numbers.created_at) as date, COUNT(*) as count')
                    ->groupBy('transaction_name', 'date')
                    ->orderBy('transaction_name')
                    ->orderBy('date')
                    ->get();

                // Wait times per transaction
                $txWaits = (clone $perTxBase)
                    ->whereIn('queue_numbers.status', ['completed', 'successful'])
                    ->selectRaw('transactions.name as transaction_name, DATE(COALESCE(queue_numbers.completed_at, queue_numbers.created_at)) as date, AVG(TIMESTAMPDIFF(MINUTE, queue_numbers.created_at, COALESCE(queue_numbers.completed_at, queue_numbers.updated_at))) as average_wait')
                    ->groupBy('transaction_name', 'date')
                    ->orderBy('transaction_name')
                    ->orderBy('date')
                    ->get();

                // Canceled per transaction
                $txCanceled = (clone $perTxBase)
                    ->whereIn('queue_numbers.status', ['failed', 'canceled'])
                    ->selectRaw('transactions.name as transaction_name, DATE(queue_numbers.created_at) as date, COUNT(*) as count')
                    ->groupBy('transaction_name', 'date')
                    ->orderBy('transaction_name')
                    ->orderBy('date')
                    ->get();
            }

            $byTransaction = [];
            foreach ($txCounts as $row) {
                $name = $row->transaction_name;
                if (!isset($byTransaction[$name])) {
                    $byTransaction[$name] = [
                        'transactions' => [],
                        'wait_times' => [],
                        'canceled_transactions' => [],
                    ];
                }
                $byTransaction[$name]['transactions'][] = ['date' => $row->date, 'count' => (int)$row->count];
            }
            foreach ($txWaits as $row) {
                $name = $row->transaction_name;
                if (!isset($byTransaction[$name])) {
                    $byTransaction[$name] = [
                        'transactions' => [],
                        'wait_times' => [],
                        'canceled_transactions' => [],
                    ];
                }
                $byTransaction[$name]['wait_times'][] = ['date' => $row->date, 'average_wait' => (float)$row->average_wait];
            }
            foreach ($txCanceled as $row) {
                $name = $row->transaction_name;
                if (!isset($byTransaction[$name])) {
                    $byTransaction[$name] = [
                        'transactions' => [],
                        'wait_times' => [],
                        'canceled_transactions' => [],
                    ];
                }
                $byTransaction[$name]['canceled_transactions'][] = ['date' => $row->date, 'count' => (int)$row->count];
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'transactions' => $transactions,
                    'wait_times' => $waitTimes,
                    'canceled_transactions' => $canceledTransactions,
                    'cancellation_reasons' => $cancellationReasons,
                    'kiosk_usage' => $kioskUsage ?: ['web' => 0, 'physical' => 0],
                    'by_transaction' => $byTransaction,
                    'durations' => [
                        'average_completed_minutes' => round($avgCompletedDuration ?? 0, 2),
                        'average_canceled_minutes' => round($avgCanceledDuration ?? 0, 2),
                    ],
                    'transaction_logs' => $transactionLogs
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching historical analytics: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch historical analytics'
            ], 500);
        }
    }

    /**
     * Legacy method for backward compatibility
     */
    public function query(Request $request)
    {
        return response()->json([
            'status' => 'success',
            'message' => 'This endpoint is deprecated. Use specific analytics endpoints instead.'
        ]);
    }

    /**
     * Superadmin consolidated reports across all departments with date filtering
     */
    public function superAdminReports(Request $request)
    {
        try {
            $user = $request->user();
            if (!($user instanceof \App\Models\Admin) || $user->role !== 'super_admin') {
                return response()->json(['message' => 'Forbidden'], 403);
            }

            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');

            $query = DB::table('queue_numbers')
                ->join('departments', 'queue_numbers.department_id', '=', 'departments.id')
                ->select(
                    'departments.id as department_id',
                    'departments.name as department_name',
                    DB::raw('COUNT(*) as total_tickets'),
                    DB::raw('COUNT(CASE WHEN queue_numbers.status IN ("completed", "successful") THEN 1 END) as successful'),
                    DB::raw('COUNT(CASE WHEN queue_numbers.status IN ("failed", "canceled") THEN 1 END) as failed'),
                    DB::raw('COUNT(CASE WHEN queue_numbers.status = "waiting" THEN 1 END) as waiting'),
                    DB::raw('COUNT(CASE WHEN queue_numbers.status = "pending" THEN 1 END) as pending'),
                    DB::raw('ROUND(AVG(TIMESTAMPDIFF(MINUTE, queue_numbers.created_at, COALESCE(queue_numbers.completed_at, queue_numbers.updated_at))), 2) as average_wait_time')
                )
                ->groupBy('departments.id', 'departments.name');

            if ($startDate) {
                $query->where('queue_numbers.created_at', '>=', Carbon::parse($startDate)->startOfDay());
            }
            if ($endDate) {
                $query->where('queue_numbers.created_at', '<=', Carbon::parse($endDate)->endOfDay());
            }
            if (!$startDate && !$endDate) {
                $query->whereDate('queue_numbers.created_at', Carbon::today());
            }

            $byDepartment = $query->get();

            // Overall totals
            $overallQuery = DB::table('queue_numbers');
            if ($startDate) {
                $overallQuery->where('created_at', '>=', Carbon::parse($startDate)->startOfDay());
            }
            if ($endDate) {
                $overallQuery->where('created_at', '<=', Carbon::parse($endDate)->endOfDay());
            }
            if (!$startDate && !$endDate) {
                $overallQuery->whereDate('created_at', Carbon::today());
            }

            $overall = $overallQuery->selectRaw('
                COUNT(*) as total_tickets,
                COUNT(CASE WHEN status IN ("completed", "successful") THEN 1 END) as successful,
                COUNT(CASE WHEN status IN ("failed", "canceled") THEN 1 END) as failed,
                COUNT(CASE WHEN status = "waiting" THEN 1 END) as waiting,
                COUNT(CASE WHEN status = "pending" THEN 1 END) as pending,
                ROUND(AVG(TIMESTAMPDIFF(MINUTE, created_at, COALESCE(completed_at, updated_at))), 2) as average_wait_time
            ')->first();

            // Top transactions overall
            $txQuery = DB::table('queue_numbers')
                ->join('transactions', 'queue_numbers.transaction_id', '=', 'transactions.id')
                ->select(
                    'transactions.name as transaction_name',
                    DB::raw('COUNT(*) as total')
                )
                ->groupBy('transactions.name')
                ->orderByDesc('total')
                ->limit(10);
            if ($startDate) {
                $txQuery->where('queue_numbers.created_at', '>=', Carbon::parse($startDate)->startOfDay());
            }
            if ($endDate) {
                $txQuery->where('queue_numbers.created_at', '<=', Carbon::parse($endDate)->endOfDay());
            }
            if (!$startDate && !$endDate) {
                $txQuery->whereDate('queue_numbers.created_at', Carbon::today());
            }
            $topTransactions = $txQuery->get();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'overall' => $overall ?: [
                        'total_tickets' => 0,
                        'successful' => 0,
                        'failed' => 0,
                        'waiting' => 0,
                        'pending' => 0,
                        'average_wait_time' => 0,
                    ],
                    'by_department' => $byDepartment,
                    'top_transactions' => $topTransactions,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching superadmin reports: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch superadmin reports',
            ], 500);
        }
    }

    /**
     * Per-department reports for admin users (or filter by department_id)
     */
    public function departmentReports(Request $request)
    {
        try {
            $user = $request->user();
            $departmentId = (int) $request->get('department_id');
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');

            if (!$departmentId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'department_id is required'
                ], 422);
            }

            // Access control: admins can only access their own department
            if ($user instanceof \App\Models\Admin && $user->role === 'admin' && (int)($user->department_id) !== $departmentId) {
                return response()->json(['message' => 'Forbidden'], 403);
            }

            $base = DB::table('queue_numbers')
                ->where('department_id', $departmentId);

            if ($startDate) {
                $base->where('created_at', '>=', Carbon::parse($startDate)->startOfDay());
            }
            if ($endDate) {
                $base->where('created_at', '<=', Carbon::parse($endDate)->endOfDay());
            }
            if (!$startDate && !$endDate) {
                $base->whereDate('created_at', Carbon::today());
            }

            $summary = (clone $base)->selectRaw('
                COUNT(*) as total_tickets,
                COUNT(CASE WHEN status IN ("completed", "successful") THEN 1 END) as successful,
                COUNT(CASE WHEN status IN ("failed", "canceled") THEN 1 END) as failed,
                COUNT(CASE WHEN status = "waiting" THEN 1 END) as waiting,
                COUNT(CASE WHEN status = "pending" THEN 1 END) as pending,
                ROUND(AVG(TIMESTAMPDIFF(MINUTE, created_at, COALESCE(completed_at, updated_at))), 2) as average_wait_time
            ')->first();

            // Breakdown by transaction
            $byTransaction = DB::table('queue_numbers')
                ->join('transactions', 'queue_numbers.transaction_id', '=', 'transactions.id')
                ->where('queue_numbers.department_id', $departmentId)
                ->select(
                    'transactions.name as transaction_name',
                    DB::raw('COUNT(*) as total'),
                    DB::raw('COUNT(CASE WHEN queue_numbers.status IN ("completed", "successful") THEN 1 END) as successful'),
                    DB::raw('COUNT(CASE WHEN queue_numbers.status IN ("failed", "canceled") THEN 1 END) as failed'),
                    DB::raw('ROUND(AVG(TIMESTAMPDIFF(MINUTE, queue_numbers.created_at, COALESCE(queue_numbers.completed_at, queue_numbers.updated_at))), 2) as average_wait_time')
                )
                ->groupBy('transactions.name')
                ->orderByDesc('total');

            if ($startDate) {
                $byTransaction->where('queue_numbers.created_at', '>=', Carbon::parse($startDate)->startOfDay());
            }
            if ($endDate) {
                $byTransaction->where('queue_numbers.created_at', '<=', Carbon::parse($endDate)->endOfDay());
            }
            if (!$startDate && !$endDate) {
                $byTransaction->whereDate('queue_numbers.created_at', Carbon::today());
            }

            $transactionBreakdown = $byTransaction->get();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'summary' => $summary ?: [
                        'total_tickets' => 0,
                        'successful' => 0,
                        'failed' => 0,
                        'waiting' => 0,
                        'pending' => 0,
                        'average_wait_time' => 0,
                    ],
                    'by_transaction' => $transactionBreakdown,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching department reports: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch department reports',
            ], 500);
        }
    }

    /**
     * Get comparison data for analytics
     */
    public function comparison(Request $request)
    {
        try {
            $user = $request->user();
            $comparisonType = $request->get('comparison_type', 'time'); // 'time' or 'department'
            $departmentId1 = $request->get('department_id_1');
            $departmentId2 = $request->get('department_id_2');
            $period1 = $request->get('period_1', 'month');
            $period2 = $request->get('period_2', 'week');

            // Access control
            if ($user instanceof \App\Models\Admin && $user->role === 'admin') {
                // Admin can only access their own department
                if ($departmentId1 && (int)($user->department_id) !== (int)$departmentId1) {
                    return response()->json(['message' => 'Forbidden'], 403);
                }
                if ($departmentId2 && (int)($user->department_id) !== (int)$departmentId2) {
                    return response()->json(['message' => 'Forbidden'], 403);
                }
            }

            // Calculate date ranges
            $dateRanges = [
                'day' => Carbon::now()->subDay(),
                'week' => Carbon::now()->subWeek(),
                'month' => Carbon::now()->subMonth(),
                '6months' => Carbon::now()->subMonths(6),
                'year' => Carbon::now()->subYear()
            ];

            $startDate1 = $dateRanges[$period1] ?? Carbon::now()->subMonth();
            $startDate2 = $dateRanges[$period2] ?? Carbon::now()->subWeek();

            // Fetch data for both periods/departments
            $data1 = $this->getComparisonData($departmentId1, $startDate1);
            $data2 = $this->getComparisonData($departmentId2, $startDate2);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'comparison_type' => $comparisonType,
                    'period_1' => $period1,
                    'period_2' => $period2,
                    'department_1' => $departmentId1,
                    'department_2' => $departmentId2,
                    'data_1' => $data1,
                    'data_2' => $data2
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching comparison data: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch comparison data'
            ], 500);
        }
    }

    /**
     * Helper method to get comparison data for a specific department and date range
     */
    private function getComparisonData($departmentId, $startDate)
    {
        $baseQuery = DB::table('queue_numbers')
            ->where('created_at', '>=', $startDate);

        if ($departmentId) {
            $baseQuery->where('department_id', $departmentId);
        }

        // Get basic metrics
        $metrics = (clone $baseQuery)->selectRaw('
            COUNT(*) as total_transactions,
            COUNT(CASE WHEN status IN ("completed", "successful") THEN 1 END) as completed_transactions,
            COUNT(CASE WHEN status IN ("failed", "canceled") THEN 1 END) as canceled_transactions,
            AVG(TIMESTAMPDIFF(MINUTE, created_at, COALESCE(completed_at, updated_at))) as average_wait_time
        ')->first();

        // Get daily breakdown
        $dailyData = (clone $baseQuery)->selectRaw('
            DATE(created_at) as date,
            COUNT(*) as transactions,
            COUNT(CASE WHEN status IN ("completed", "successful") THEN 1 END) as completed,
            COUNT(CASE WHEN status IN ("failed", "canceled") THEN 1 END) as canceled,
            AVG(TIMESTAMPDIFF(MINUTE, created_at, COALESCE(completed_at, updated_at))) as avg_wait
        ')
        ->groupBy('date')
        ->orderBy('date')
        ->get();

        // Get wait times for completed transactions
        $waitTimes = (clone $baseQuery)->whereIn('status', ['completed', 'successful'])
            ->selectRaw('
                DATE(COALESCE(completed_at, created_at)) as date,
                AVG(TIMESTAMPDIFF(MINUTE, created_at, COALESCE(completed_at, updated_at))) as avg_wait
            ')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Get canceled transactions
        $canceledData = (clone $baseQuery)->whereIn('status', ['failed', 'canceled'])
            ->selectRaw('
                DATE(created_at) as date,
                COUNT(*) as count
            ')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'metrics' => $metrics ?: [
                'total_transactions' => 0,
                'completed_transactions' => 0,
                'canceled_transactions' => 0,
                'average_wait_time' => 0
            ],
            'daily_data' => $dailyData,
            'wait_times' => $waitTimes,
            'canceled_data' => $canceledData
        ];
    }

    /**
     * Get canceled transactions for a department
     */
    public function departmentCanceledTransactions(Request $request)
    {
        try {
            $user = $request->user();
            $departmentId = (int) $request->get('department_id');
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');
            $status = $request->get('status', 'canceled');

            if (!$departmentId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'department_id is required'
                ], 422);
            }

            // Access control: admins can only access their own department
            if ($user instanceof \App\Models\Admin && $user->role === 'admin' && (int)($user->department_id) !== $departmentId) {
                return response()->json(['message' => 'Forbidden'], 403);
            }

            $query = DB::table('queue_numbers')
                ->join('transactions', 'queue_numbers.transaction_id', '=', 'transactions.id')
                ->join('departments', 'queue_numbers.department_id', '=', 'departments.id')
                ->where('queue_numbers.department_id', $departmentId)
                ->whereIn('queue_numbers.status', ['canceled', 'failed']);

            if ($startDate) {
                $query->where('queue_numbers.created_at', '>=', Carbon::parse($startDate)->startOfDay());
            }
            if ($endDate) {
                $query->where('queue_numbers.created_at', '<=', Carbon::parse($endDate)->endOfDay());
            }
            if (!$startDate && !$endDate) {
                $query->whereDate('queue_numbers.created_at', Carbon::today());
            }

            $canceledTransactions = $query->select(
                'queue_numbers.id',
                'queue_numbers.queue_number',
                'queue_numbers.full_queue_number',
                'queue_numbers.status',
                DB::raw('COALESCE(queue_numbers.cancel_reason, "No reason provided") as cancellation_reason'),
                DB::raw('COALESCE(queue_numbers.cancel_reason, "") as cancellation_notes'),
                'queue_numbers.created_at',
                'queue_numbers.updated_at',
                'queue_numbers.completed_at',
                'transactions.name as transaction_name',
                'departments.name as department_name'
            )
            ->orderBy('queue_numbers.created_at', 'desc')
            ->get();

            return response()->json([
                'status' => 'success',
                'data' => $canceledTransactions
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching canceled transactions: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch canceled transactions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available cancellation reasons for dropdowns
     */
    public function getCancellationReasons(Request $request)
    {
        try {
            $user = $request->user();
            $departmentId = (int) $request->get('department_id');

            if (!$departmentId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'department_id is required'
                ], 422);
            }

            // Access control: admins can only access their own department
            if ($user instanceof \App\Models\Admin && $user->role === 'admin' && (int)($user->department_id) !== $departmentId) {
                return response()->json(['message' => 'Forbidden'], 403);
            }

            // Get unique cancellation reasons from the database
            $reasons = DB::table('queue_numbers')
                ->where('department_id', $departmentId)
                ->whereIn('status', ['canceled', 'failed'])
                ->whereNotNull('cancel_reason')
                ->where('cancel_reason', '!=', '')
                ->distinct()
                ->pluck('cancel_reason')
                ->filter()
                ->sort()
                ->values();

            // Add common default reasons if none exist in database
            $defaultReasons = [
                'Missing required documents',
                'Incorrect information provided',
                'User requested cancellation',
                'Technical system error',
                'Service temporarily unavailable',
                'Payment issues',
                'Incomplete application',
                'Time limit exceeded',
                'No show',
                'Other'
            ];

            // Merge database reasons with defaults, removing duplicates
            $allReasons = collect($reasons)
                ->merge($defaultReasons)
                ->unique()
                ->sort()
                ->values();

            return response()->json([
                'status' => 'success',
                'data' => $allReasons
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching cancellation reasons: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch cancellation reasons'
            ], 500);
        }
    }
}
