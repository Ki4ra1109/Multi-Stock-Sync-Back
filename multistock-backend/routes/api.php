<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AuthController;

use App\Http\Controllers\ClientesController;

use App\Http\Controllers\BrandsController;
use App\Http\Controllers\StockController;

use App\Http\Controllers\WarehouseCompaniesController;

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
Route::get('/marcas', [BrandsController::class, 'index']); // Get all brands
Route::post('/marcas', [BrandsController::class, 'store']); // Create a brand
Route::get('/marcas/{id}', [BrandsController::class, 'show']); // Get a brand
Route::put('/marcas/{id}', [BrandsController::class, 'update']); // Update a brand
Route::patch('/marcas/{id}', [BrandsController::class, 'patch']); // Patch a brand
Route::delete('/marcas/{id}', [BrandsController::class, 'destroy']); // Delete a brand


// CRUD routes for companies
Route::get('/companies', [WarehouseCompaniesController::class, 'index']); // Get all companies
Route::post('/companies', [WarehouseCompaniesController::class, 'store']); // Create a company
Route::get('/companies/{id}', [WarehouseCompaniesController::class, 'show']); // Get a company
Route::patch('/companies/{id}', [WarehouseCompaniesController::class, 'update']); // Update a company
Route::delete('/companies/{id}', [WarehouseCompaniesController::class, 'destroy']); // Delete a company

// CRUD routes for warehouses
Route::get('/warehouses', [WarehouseCompaniesController::class, 'index']); // Get all warehouses
Route::post('/warehouses', [WarehouseCompaniesController::class, 'store']); // Create a warehouse
Route::get('/warehouses/{id}', [WarehouseCompaniesController::class, 'show']); // Get a warehouse
Route::patch('/warehouses/{id}', [WarehouseCompaniesController::class, 'update']); // Update a warehouse
Route::delete('/warehouses/{id}', [WarehouseCompaniesController::class, 'destroy']); // Delete a warehouse

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
