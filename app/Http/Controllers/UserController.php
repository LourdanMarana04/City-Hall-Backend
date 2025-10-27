<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    // Update user (admin)
    public function update(Request $request, $id)
    {
        $authUser = $request->user();
        $user = Admin::findOrFail($id);
        // Only superadmins can update admin/super_admin accounts
        if (!($authUser instanceof Admin && $authUser->role === 'super_admin')) {
            // Regular admins can only update their own profile (not password)
            if (!($authUser instanceof Admin && $authUser->role === 'admin' && $authUser->id === $user->id)) {
                return response()->json(['error' => 'Forbidden'], 403);
            }
        }
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255|unique:admins,email,' . $id,
            'department_id' => 'nullable|exists:departments,id',
            'password' => 'nullable|string|min:8|confirmed',
        ]);
        // Only superadmins can change admin/superadmin passwords
        if (isset($data['password'])) {
            if (!($authUser instanceof Admin && $authUser->role === 'super_admin')) {
                return response()->json(['error' => 'Forbidden'], 403);
            }
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }
        $user->update($data);
        Log::info('User updated', [
            'by_user_id' => $authUser ? $authUser->id : null,
            'by_user_role' => $authUser instanceof Admin ? $authUser->role : null,
            'target_user_id' => $user->id,
            'target_user_role' => $user->role,
            'action' => 'update',
        ]);
        return response()->json(['status' => true, 'message' => 'User updated', 'data' => $user]);
    }

    // Delete user (admin)
    public function destroy(Request $request, $id)
    {
        $authUser = $request->user();
        $user = Admin::findOrFail($id);
        // Only superadmins can delete admins
        if (!($authUser instanceof Admin && $authUser->role === 'super_admin')) {
            // Regular admins can only delete their own account
            if (!($authUser instanceof Admin && $authUser->role === 'admin' && $authUser->id === $user->id)) {
                return response()->json(['error' => 'Forbidden'], 403);
            }
        }

        // Revoke all tokens for the user being deleted
        $user->tokens()->delete();

        Log::info('User deleted and tokens revoked', [
            'by_user_id' => $authUser ? $authUser->id : null,
            'by_user_role' => $authUser instanceof Admin ? $authUser->role : null,
            'target_user_id' => $user->id,
            'target_user_role' => $user->role,
            'tokens_revoked' => true,
            'action' => 'delete',
        ]);

        $user->delete();
        return response()->json(['status' => true, 'message' => 'User deleted and all sessions terminated']);
    }

    // Revoke all tokens for a user (useful for deactivating accounts)
    public function revokeAllTokens(Request $request, $id)
    {
        $authUser = $request->user();
        $user = Admin::findOrFail($id);

        // Only superadmins can revoke tokens for other users
        if (!($authUser instanceof Admin && $authUser->role === 'super_admin')) {
            // Regular admins can only revoke their own tokens
            if (!($authUser instanceof Admin && $authUser->role === 'admin' && $authUser->id === $user->id)) {
                return response()->json(['error' => 'Forbidden'], 403);
            }
        }

        $tokenCount = $user->tokens()->count();
        $user->tokens()->delete();

        Log::info('All tokens revoked for user', [
            'by_user_id' => $authUser ? $authUser->id : null,
            'by_user_role' => $authUser instanceof Admin ? $authUser->role : null,
            'target_user_id' => $user->id,
            'target_user_role' => $user->role,
            'tokens_revoked' => $tokenCount,
            'action' => 'revoke_tokens',
        ]);

        return response()->json([
            'status' => true,
            'message' => "All sessions terminated for {$user->name}",
            'tokens_revoked' => $tokenCount
        ]);
    }

    // List all users
    public function index()
    {
        $admins = Admin::all();
        return response()->json($admins);
    }
}
