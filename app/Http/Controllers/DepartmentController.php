<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Department;
use App\Models\SystemChange;
use App\Models\Transaction;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return Department::with('transactions')->get();
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $user = $request->user();
        $department = Department::create($request->only(['name', 'description', 'active']));
        // Create transactions if provided
        if ($request->has('transactions') && is_array($request->transactions)) {
            foreach ($request->transactions as $txName) {
                if (trim($txName) !== '') {
                    $department->transactions()->create([
                        'name' => $txName,
                        'archived' => false,
                        'requirements' => [],
                        'procedures' => [],
                    ]);
                }
            }
        }
        // Return department with transactions
        $this->broadcastSystemChange(
            $user,
            ['admin'],
            'department_created',
            sprintf('Department "%s" was added.', $department->name),
            [
                'department_id' => $department->id,
                'department_name' => $department->name,
            ]
        );

        return response()->json($department->load('transactions'), 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Department  $department
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $user = request()->user();
        $department = Department::with('transactions')->findOrFail($id);

        // Only check department access if user is authenticated and is an admin
        if ($user instanceof \App\Models\Admin && $user->role === 'admin') {
            if ($user->department_id !== $department->id) {
                return response()->json(['error' => 'Forbidden'], 403);
            }
        }

        return $department;
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Department  $department
     * @return \Illuminate\Http\Response
     */
    public function edit(Department $department)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Department  $department
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();
        $department = Department::findOrFail($id);

        // Only check department access if user is an admin
        if ($user instanceof \App\Models\Admin && $user->role === 'admin') {
            if ($user->department_id !== $department->id) {
                return response()->json(['error' => 'Forbidden'], 403);
            }
        }

        $department->update($request->only(['name', 'description', 'active']));
        $this->broadcastSystemChange(
            $user,
            ['admin'],
            'department_updated',
            sprintf('Department "%s" was updated.', $department->name),
            [
                'department_id' => $department->id,
                'department_name' => $department->name,
            ]
        );
        return response()->json($department);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Department  $department
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $department = Department::findOrFail($id);

        // Only check department access if user is an admin
        if ($user instanceof \App\Models\Admin && $user->role === 'admin') {
            if ($user->department_id !== $department->id) {
                return response()->json(['error' => 'Forbidden'], 403);
            }
        }

        $department->delete();
        $this->broadcastSystemChange(
            $user,
            ['admin'],
            'department_deleted',
            sprintf('Department "%s" was removed.', $department->name),
            [
                'department_id' => $department->id,
                'department_name' => $department->name,
            ]
        );
        return response()->json(null, 204);
    }

    public function storeTransaction(Request $request, $departmentId)
    {
        $user = $request->user();
        $department = Department::findOrFail($departmentId);

        // Only check department access if user is an admin
        if ($user instanceof \App\Models\Admin && $user->role === 'admin') {
            if ($user->department_id !== $department->id) {
                return response()->json(['error' => 'Forbidden'], 403);
            }
        }

        $transaction = $department->transactions()->create($request->only(['name', 'archived', 'requirements', 'procedures']));
        $this->broadcastSystemChange(
            $user,
            ['admin'],
            'transaction_created',
            sprintf('A new transaction "%s" was added under "%s".', $transaction->name, $department->name),
            [
                'department_id' => $department->id,
                'department_name' => $department->name,
                'transaction_id' => $transaction->id,
                'transaction_name' => $transaction->name,
            ]
        );
        return response()->json($transaction, 201);
    }

    public function updateTransaction(Request $request, $departmentId, $transactionId)
    {
        $user = $request->user();
        $department = Department::findOrFail($departmentId);

        // Only check department access if user is an admin
        if ($user instanceof \App\Models\Admin && $user->role === 'admin') {
            if ($user->department_id !== $department->id) {
                return response()->json(['error' => 'Forbidden'], 403);
            }
        }

        $transaction = $department->transactions()->findOrFail($transactionId);
        $transaction->update($request->only(['name', 'requirements', 'procedures']));
        $this->broadcastSystemChange(
            $user,
            ['admin'],
            'transaction_updated',
            sprintf('Transaction "%s" was updated under "%s".', $transaction->name, $department->name),
            [
                'department_id' => $department->id,
                'department_name' => $department->name,
                'transaction_id' => $transaction->id,
                'transaction_name' => $transaction->name,
            ]
        );
        return response()->json($transaction);
    }

    /**
     * Broadcast a system change notification for polling clients.
     */
    protected function broadcastSystemChange($user, array $scopes, string $action, ?string $message = null, array $metadata = []): void
    {
        if (!($user instanceof Admin) || $user->role !== 'super_admin') {
            return;
        }

        foreach ($scopes as $scope) {
            if (!in_array($scope, ['admin', 'kiosk-user', 'web-user-kiosk'], true)) {
                continue;
            }

            SystemChange::create([
                'actor_id' => $user->id,
                'actor_name' => $user->name,
                'actor_role' => $user->role,
                'scope' => $scope,
                'action' => $action,
                'message' => $message,
                'metadata' => $metadata,
            ]);
        }
    }
}
