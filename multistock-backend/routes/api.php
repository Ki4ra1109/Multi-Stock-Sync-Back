<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductosController;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Public routes
Route::post('/login', [AuthController::class, 'login']); // Login user
Route::post('/users', [UserController::class, 'store']); // Create user
Route::get('/productos', [ProductosController::class, 'index']); // Get all products
Route::post('/productos', [ProductosController::class, 'store']); // Create a product
Route::get('/productos/{id}', [ProductosController::class, 'show']); // Get a product
Route::delete('/productos/{id}', [ProductosController::class, 'destroy']); // Delete a product

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/users', [UserController::class, 'index']); // Get full users list
    Route::post('/logout', [AuthController::class, 'logout']); // Logout user
});
