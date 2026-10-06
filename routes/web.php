<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'success' => true,
        'message' => 'PROXIWORK API is running.',
        'data' => ['name' => 'PROXIWORK', 'version' => '1.0.0'],
    ]);
});

Route::get('/reset-password/{token}', function (Request $request, string $token) {
    return response()->json([
        'success' => true,
        'message' => 'Password reset link received.',
        'data' => ['token' => $token, 'email' => $request->query('email')],
    ]);
})->middleware('guest')->name('password.reset');
