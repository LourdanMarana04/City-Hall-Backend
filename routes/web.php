<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return response()->json([
        'message' => 'City Hall Queue Management System API',
        'version' => '1.0.0',
        'status' => 'running'
    ]);
});

// Add a dummy login route to prevent "Route [login] not defined" errors
Route::get('/login', function () {
    return response()->json([
        'error' => 'Unauthenticated',
        'message' => 'Please use /api/login for authentication'
    ], 401);
})->name('login');
