<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductosController;
use App\Http\Controllers\ClientesController;
use App\Http\Controllers\TipoProductoController;
use App\Http\Controllers\MarcasController;
use App\Http\Controllers\PackProductosController;
use App\Http\Controllers\StockController;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Public routes
Route::post('/login', [AuthController::class, 'login']); // Login user
Route::post('/users', [UserController::class, 'store']); // Create user

// CRUD routes for Productos and Clientes
Route::get('/productos', [ProductosController::class, 'index']); // Get all products
Route::post('/productos', [ProductosController::class, 'store']); // Create a product
Route::get('/productos/{id}', [ProductosController::class, 'show']); // Get a product
Route::patch('/productos/{id}', [ProductosController::class, 'patch']); // Patch a product
Route::delete('/productos/{id}', [ProductosController::class, 'destroy']); // Delete a product

// CRUD routes for Clientes
Route::get('/clientes', [ClientesController::class, 'index']); // Get all clients
Route::post('/clientes', [ClientesController::class, 'store']); // Create a client
Route::get('/clientes/{id}', [ClientesController::class, 'show']); // Get a client
Route::patch('/clientes/{id}', [ClientesController::class, 'update']); // Update a client
Route::delete('/clientes/{id}', [ClientesController::class, 'destroy']); // Delete a client

// CRUD routes for TipoProductos
Route::get('/tipo-productos', [TipoProductoController::class, 'index']); // Get all tipo productos
Route::post('/tipo-productos', [TipoProductoController::class, 'store']); // Create a tipo producto
Route::get('/tipo-productos/{id}', [TipoProductoController::class, 'show']); // Get a tipo producto
Route::put('/tipo-productos/{id}', [TipoProductoController::class, 'update']); // Update a tipo producto
Route::delete('/tipo-productos/{id}', [TipoProductoController::class, 'destroy']); // Delete a tipo producto

// CRUD routes for Marcas
Route::get('/marcas', [MarcasController::class, 'index']); // Get all marcas
Route::post('/marcas', [MarcasController::class, 'store']); // Create a marca
Route::get('/marcas/{id}', [MarcasController::class, 'show']); // Get a marca
Route::put('/marcas/{id}', [MarcasController::class, 'update']); // Update a marca
Route::delete('/marcas/{id}', [MarcasController::class, 'destroy']); // Delete a marca

// CRUD routes for PackProductos
Route::get('/pack-productos', [PackProductosController::class, 'index']); // Get all packs
Route::post('/pack-productos', [PackProductosController::class, 'store']); // Create a pack
Route::get('/pack-productos/{id}', [PackProductosController::class, 'show']); // Get a pack
Route::put('/pack-productos/{id}', [PackProductosController::class, 'update']); // Update a pack
Route::delete('/pack-productos/{id}', [PackProductosController::class, 'destroy']); // Delete a pack

// CRUD routes for Stock
Route::get('/stock', [StockController::class, 'index']); // Get all stock records
Route::post('/stock', [StockController::class, 'store']); // Create a new stock record
Route::get('/stock/{id}', [StockController::class, 'show']); // Get a stock record by ID
Route::put('/stock/{id}', [StockController::class, 'update']); // Update stock (add/subtract units)
Route::delete('/stock/{id}', [StockController::class, 'destroy']); // Delete a stock record

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/users', [UserController::class, 'index']); // Get full users list
    Route::post('/logout', [AuthController::class, 'logout']); // Logout user
});
