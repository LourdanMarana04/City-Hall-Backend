<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\SystemChange;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class SystemChangeController extends Controller
{
    /**
     * Display a listing of system changes for polling clients.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'scope' => ['nullable', 'string', Rule::in(['admin', 'kiosk-user', 'web-user-kiosk'])],
            'since' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'max_age_minutes' => ['nullable', 'integer', 'min:1', 'max:10080'], // up to 7 days
        ]);

        $query = SystemChange::query()
            ->latest();

        if (!empty($validated['scope'])) {
            $query->where('scope', $validated['scope']);
        }

        if (!empty($validated['since'])) {
            $query->where('created_at', '>', $validated['since']);
        }

        // Apply automatic expiry window to avoid returning stale announcements on refresh.
        // Default retention: 30 minutes unless overridden by query param.
        $retentionMinutes = (int)($validated['max_age_minutes'] ?? 30);
        $query->where('created_at', '>', now()->subMinutes($retentionMinutes));

        $changes = $query
            ->limit($validated['limit'] ?? 20)
            ->get()
            ->map(function (SystemChange $change) {
                return [
                    'id' => $change->id,
                    'scope' => $change->scope,
                    'action' => $change->action,
                    'message' => $change->message,
                    'metadata' => $change->metadata,
                    'actor' => [
                        'id' => $change->actor_id,
                        'name' => $change->actor_name,
                        'role' => $change->actor_role,
                    ],
                    'created_at' => $change->created_at?->toIso8601String(),
                ];
            });

        return response()->json([
            'data' => $changes,
            'polled_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Store a newly created system change.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if (!($user instanceof Admin) || $user->role !== 'super_admin') {
            return response()->json([
                'message' => 'Only super administrators can publish system change notifications.',
            ], 403);
        }

        $validated = $request->validate([
            'scope' => ['required', 'string', Rule::in(['admin', 'kiosk-user', 'web-user-kiosk'])],
            'action' => ['required', 'string', 'max:191'],
            'message' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ]);

        $change = SystemChange::create([
            'actor_id' => $user->id,
            'actor_name' => $user->name,
            'actor_role' => $user->role,
            'scope' => $validated['scope'],
            'action' => $validated['action'],
            'message' => $validated['message'] ?? null,
            'metadata' => $validated['metadata'] ?? null,
        ]);

        Log::info('System change recorded', [
            'change_id' => $change->id,
            'scope' => $change->scope,
            'action' => $change->action,
            'actor_id' => $user->id,
        ]);

        return response()->json([
            'message' => 'System change notification recorded successfully.',
            'data' => [
                'id' => $change->id,
                'scope' => $change->scope,
                'action' => $change->action,
                'message' => $change->message,
                'metadata' => $change->metadata,
                'created_at' => $change->created_at?->toIso8601String(),
            ],
        ], 201);
    }
}

