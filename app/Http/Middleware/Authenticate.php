<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo($request)
    {
        // Since this is an API-only backend, we don't redirect to a login page
        // Instead, we let the middleware handle the JSON response
        return null;
    }

    /**
     * Handle an unauthenticated user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  array  $guards
     * @return void
     *
     * @throws \Illuminate\Auth\AuthenticationException
     */
    protected function unauthenticated($request, array $guards)
    {
        // For API requests, return JSON response instead of redirecting
        if ($request->expectsJson()) {
            throw new \Illuminate\Auth\AuthenticationException(
                'Unauthenticated.', $guards, $this->redirectTo($request)
            );
        }

        // For web requests, redirect to login (though we don't have web routes)
        throw new \Illuminate\Auth\AuthenticationException(
            'Unauthenticated.', $guards, $this->redirectTo($request)
        );
    }
}
