<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Admin;
use App\Models\Citizen;
use Illuminate\Support\Facades\Auth;

class CheckUserExists
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user) {
            // Check if user still exists in database
            $userExists = false;
            $isActive = true;

            if ($user instanceof Admin) {
                $dbUser = Admin::find($user->id);
                if ($dbUser) {
                    $userExists = true;
                    $isActive = $dbUser->is_active;
                }
            } elseif ($user instanceof Citizen) {
                $dbUser = Citizen::find($user->id);
                if ($dbUser) {
                    $userExists = true;
                    $isActive = true; // Citizens don't have is_active field
                }
            }

            // If user doesn't exist or is inactive, revoke token and return unauthorized
            if (!$userExists || !$isActive) {
                // Revoke current token
                $user->currentAccessToken()->delete();

                return response()->json([
                    'status' => false,
                    'message' => 'Account has been deleted or deactivated',
                    'code' => 'ACCOUNT_DELETED'
                ], 401);
            }
        }

        return $next($request);
    }
}

