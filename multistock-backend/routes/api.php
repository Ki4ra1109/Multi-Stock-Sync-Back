<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AuthController;

use App\Http\Controllers\ClientesController;

use App\Http\Controllers\MarcasController;
use App\Http\Controllers\StockController;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Public routes
Route::post('/login', [AuthController::class, 'login']); // Login user
Route::post('/users', [UserController::class, 'store']); // Create user

// CRUD routes for Clientes
Route::get('/clientes', [ClientesController::class, 'index']); // Get all clients
Route::post('/clientes', [ClientesController::class, 'store']); // Create a client
Route::get('/clientes/{id}', [ClientesController::class, 'show']); // Get a client
Route::patch('/clientes/{id}', [ClientesController::class, 'update']); // Update a client
Route::delete('/clientes/{id}', [ClientesController::class, 'destroy']); // Delete a client

// CRUD routes for Marcas
Route::get('/marcas', [MarcasController::class, 'index']); // Get all marcas
Route::post('/marcas', [MarcasController::class, 'store']); // Create a marca
Route::get('/marcas/{id}', [MarcasController::class, 'show']); // Get a marca
Route::put('/marcas/{id}', [MarcasController::class, 'update']); // Update a marca
Route::patch('/marcas/{id}', [MarcasController::class, 'patch']); // Patch a marca
Route::delete('/marcas/{id}', [MarcasController::class, 'destroy']); // Delete a marca



// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/users', [UserController::class, 'index']); // Get full users list
    Route::post('/logout', [AuthController::class, 'logout']); // Logout user
});


use App\Http\Controllers\MercadoLibreController;

// Save MercadoLibre credentials
Route::post('/mercadolibre/save-credentials', [MercadoLibreController::class, 'saveCredentials']);
// Generate MerccadoLibre login Auth 2.0 URL
Route::post('/mercadolibre/login', [MercadoLibreController::class, 'login']);
// Handle MercadoLibre callback
Route::get('/mercadolibre/callback', [MercadoLibreController::class, 'handleCallback']);
// Check MercadoLibre connection status
Route::get('/mercadolibre/test-connection', [MercadoLibreController::class, 'testConnection']);
// Get MercadoLibre credentials if are saved in db
Route::get('/mercadolibre/credentials/status', [MercadoLibreController::class, 'getCredentialsStatus']);
// Logout (Delete credentials and token)
Route::post('/mercadolibre/logout', [MercadoLibreController::class, 'logout']);


use App\Http\Controllers\MercadoLibreProductController;
// Get MercadoLibre products list
Route::get('/mercadolibre/products', [MercadoLibreProductController::class, 'listProducts']);
