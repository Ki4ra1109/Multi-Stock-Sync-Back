<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AuthController;



use App\Http\Controllers\BrandsController;
use App\Http\Controllers\StockController;

use App\Http\Controllers\WarehouseCompaniesController;
use App\Http\Controllers\InfoController;

use App\Http\Controllers\MercadoLibreProductController;

/**
     * New Controllers
**/

//  REPORTES //
use App\Http\Controllers\MercadoLibre\Reportes\compareAnnualSalesDataController;
use App\Http\Controllers\MercadoLibre\Reportes\compareSalesDataController;
use App\Http\Controllers\MercadoLibre\Reportes\getAnnualSalesController;
use App\Http\Controllers\MercadoLibre\Reportes\getDailySalesController;
use App\Http\Controllers\MercadoLibre\Reportes\getInvoiceReportController;
use App\Http\Controllers\MercadoLibre\Reportes\getOrderStatusesController;
use App\Http\Controllers\MercadoLibre\Reportes\getRefundsByCategoryController;
use App\Http\Controllers\MercadoLibre\Reportes\getSalesByWeekController;
use App\Http\Controllers\MercadoLibre\Reportes\getSalesByMonthController;
use App\Http\Controllers\MercadoLibre\Reportes\getTopPaymentMethodsController;
use App\Http\Controllers\MercadoLibre\Reportes\getTopSellingProductsController;
use App\Http\Controllers\MercadoLibre\Reportes\getWeeksOfMonthController;
use App\Http\Controllers\MercadoLibre\Reportes\summaryController;
use App\Http\Controllers\MercadoLibre\Reportes\getSalesByDateRangeController;
use App\Http\Controllers\MercadoLibre\Reportes\reviewController;

// LOGIN //

use App\Http\Controllers\MercadoLibre\Login\loginController;
use App\Http\Controllers\MercadoLibre\Login\handleCallbackController;

// CONNECTIONS //

use App\Http\Controllers\MercadoLibre\Connections\testAndRefreshConnectionController;   

// CREDENTIALS //

use App\Http\Controllers\MercadoLibre\Credentials\deleteCredentialsController;
use App\Http\Controllers\MercadoLibre\Credentials\getAllCredentialsDataController;
use App\Http\Controllers\MercadoLibre\Credentials\getCredentialsByClientIdController;
use App\Http\Controllers\MercadoLibre\Credentials\refreshAccessTokenController;

//  PRODUCTS  //

use App\Http\Controllers\MercadoLibre\Products\listProductByClientIdController;
use App\Http\Controllers\MercadoLibre\Products\searchProductsController;
use App\Http\Controllers\MercadoLibre\Products\getProductReviewsController;
use App\Http\Controllers\MercadoLibre\Products\saveProductsController;

// SyncStatus //
use App\Http\Controllers\SyncStatusController;

// Public routes
Route::post('/login', [AuthController::class, 'login']); // Login user
Route::post('/users', [UserController::class, 'store']); // Create user

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']); // Logout users

    // USERS

    Route::get('/users', [UserController::class, 'usersList']); // Get full users list
    Route::get('/users/{id}', [UserController::class,'show']); // Get a user
    Route::patch('/users/{id}', [UserController::class,'update']); // Update a user
    Route::delete('/users/{id}', [UserController::class,'delete']); // Delete a user

    // SINCRONICACIÓN
    Route::post('/sincronizar', [SyncStatusController::class, 'iniciarSincronizacion']);
    Route::get('/estado-sincronizacion', [SyncStatusController::class, 'estadoSincronizacion']);

    // CRUD routes for Clientes
    Route::get('/clientes', [ClientesController::class, 'index']); // Get all clients
    Route::post('/clientes', [ClientesController::class, 'store']); // Create a client
    Route::get('/clientes/{id}', [ClientesController::class, 'show']); // Get a client
    Route::patch('/clientes/{id}', [ClientesController::class, 'update']); // Update a client
    Route::delete('/clientes/{id}', [ClientesController::class, 'destroy']); // Delete a client

    // Warehouse-specific routes
    Route::get('/warehouses', [WarehouseCompaniesController::class, 'warehouse_list_all']); // List all warehouses
    Route::post('/warehouses', [WarehouseCompaniesController::class, 'warehouse_store']); // Create a warehouse
    Route::patch('/warehouses/{id}', [WarehouseCompaniesController::class, 'warehouse_update']); // Update a warehouse
    Route::get('/warehouses/{id}', [WarehouseCompaniesController::class, 'warehouse_show']); // Get a warehouse by its ID
    Route::delete('/warehouses/{id}', [WarehouseCompaniesController::class, 'warehouse_delete']); // Delete a warehouse

    // Stock-specific routes
    Route::post('/warehouse-stock', [WarehouseCompaniesController::class, 'stock_store']); // Create stock for a warehouse
    Route::patch('/warehouse-stock/{id}', [WarehouseCompaniesController::class, 'stock_update']); // Update stock for a warehouse
    Route::delete('/warehouse-stock/{id}', [WarehouseCompaniesController::class, 'stock_delete']); // Delete stock for a warehouse

    // Generate MerccadoLibre login Auth 2.0 URL
    Route::post('/mercadolibre/login', [loginController::class, 'login']);

    // Handle MercadoLibre callback
    Route::get('/mercadolibre/callback', [handleCallbackController::class, 'handleCallback']);

    // Check MercadoLibre connection status
    Route::get('/mercadolibre/test-connection/{client_id}', [testAndRefreshConnectionController::class, 'testAndRefreshConnection']);

    // Get MercadoLibre credentials if are saved in db
    Route::get('/mercadolibre/credentials', [getAllCredentialsDataController::class, 'getAllCredentialsData']);

    // Get MercadoLibre credentials by client_id
    Route::get('/mercadolibre/credentials/{client_id}', [getCredentialsByClientIdController::class, 'getCredentialsByClientId']);

    // Delete credentials using client_id
    Route::delete('/mercadolibre/credentials/{client_id}', [deleteCredentialsController::class, 'deleteCredentials']);

    // Get MercadoLibre products list by client_id
    Route::get('/mercadolibre/products/{client_id}', [listProductByClientIdController::class, 'listProductsByClientId']);
    
    // Search MercadoLibre products by client_id and search term
    Route::get('/mercadolibre/products/search/{client_id}', [searchProductsController::class, 'searchProducts']);

    // Get product reviews by product_id
    Route::get('/mercadolibre/products/reviews/{product_id}', [getProductReviewsController::class, 'getProductReviews']);

    // Get saves products
    Route::get('/mercadolibre/save-products/{client_id}', [saveProductsController::class, 'saveProducts']);

    // Get MercadoLibre invoice report by client_id
    Route::get('/mercadolibre/invoices/{client_id}', [getInvoiceReportController::class, 'getInvoiceReport']);

    // Get refunds or returns by category
    Route::get('/mercadolibre/refunds-by-category/{client_id}', [getRefundsByCategoryController::class, 'getRefundsByCategory']);

    // Get MercadoLibre sales by month by client_id
    Route::get('/mercadolibre/sales-by-month/{client_id}', [getSalesByMonthController::class, 'getSalesByMonth']);

    // Get MercadoLibre annual sales by client_id
    Route::get('/mercadolibre/annual-sales/{client_id}', [getAnnualSalesController::class, 'getAnnualSales']);

    // Get weeks of the month
    Route::get('/mercadolibre/weeks-of-month', [getWeeksOfMonthController::class, 'getWeeksOfMonth']);

    // Get total sales for a specific week
    Route::get('/mercadolibre/sales-by-week/{client_id}', [getSalesByWeekController::class, 'getSalesByWeek']);

    // Get daily sales
    Route::get('/mercadolibre/daily-sales/{client_id}', [getDailySalesController::class, 'getDailySales']);

    // Get top selling products
    Route::get('/mercadolibre/top-selling-products/{client_id}', [getTopSellingProductsController::class, 'getTopSellingProducts']);

    // Get order statuses
    Route::get('/mercadolibre/order-statuses/{client_id}', [getOrderStatusesController::class, 'getOrderStatuses']);

    // Get top payment methods
    Route::get('/mercadolibre/top-payment-methods/{client_id}', [getTopPaymentMethodsController::class, 'getTopPaymentMethods']);

    // Get summary
    Route::get('/mercadolibre/summary/{client_id}', [summaryController::class, 'summary']);

    // Compare sales data between two months
    Route::get('/mercadolibre/compare-sales-data/{client_id}', [compareSalesDataController::class, 'compareSalesData']);

    // Compare sales data between two years
    Route::get('/mercadolibre/compare-annual-sales-data/{client_id}', [compareAnnualSalesDataController::class, 'compareAnnualSalesData']);

    // Get sales by date range
    Route::get('/mercadolibre/sales-by-date-range/{client_id}', [getSalesByDateRangeController::class, 'getSalesByDateRange']);

    // Save MercadoLibre products to database
    Route::get('/mercadolibre/save-products/{client_id}', [MercadoLibreProductController::class, 'saveProducts']);

    // Refresh MercadoLibre access token
    Route::post('/mercadolibre/refresh-token', [refreshAccessTokenController::class, 'refreshToken']);

    // REVIEWS
    Route::get('reviews/{clientId}/{productId}', [reviewController::class, 'getReviewsByClientId']);

    // ITEMS
    Route::post('/mercadolibre/items', [ItemController::class, 'store']);
    Route::put('/mercadolibre/items/{item_id}', [ItemController::class, 'update']);
});
