<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductosController;
use App\Http\Controllers\ClientesController;
use App\Http\Controllers\TipoProductoController;

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
Route::get('/clientes', [ClientesController::class, 'index']); // Get all clients
Route::post('/clientes', [ClientesController::class, 'store']); // Create a client
Route::get('/clientes/{id}', [ClientesController::class, 'show']); // Get a client
Route::put('/clientes/{id}', [ClientesController::class, 'update']); // Update a client
Route::delete('/clientes/{id}', [ClientesController::class, 'destroy']); // Delete a client
Route::get('/tipo-productos', [TipoProductoController::class, 'index']); // Get all tipo productos
Route::post('/tipo-productos', [TipoProductoController::class, 'store']); // Create a tipo producto
Route::get('/tipo-productos/{id}', [TipoProductoController::class, 'show']); // Get a tipo producto
Route::put('/tipo-productos/{id}', [TipoProductoController::class, 'update']); // Update a tipo producto
Route::delete('/tipo-productos/{id}', [TipoProductoController::class, 'destroy']); // Delete a tipo producto

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/users', [UserController::class, 'index']); // Get full users list
    Route::post('/logout', [AuthController::class, 'logout']); // Logout user
});
