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
use App\Http\Controllers\MercadoLibre\Reportes\productReportController;
use App\Http\Controllers\MercadoLibre\Reportes\getStockRotationController;
use App\Http\Controllers\MercadoLibre\Reportes\getStockReceptionController;
use App\Http\Controllers\MercadoLibre\Reportes\getAvailableForReceptionController;
use App\Http\Controllers\MercadoLibre\Reportes\getProductsToDispatchController;
use App\Http\Controllers\MercadoLibre\Reportes\getStockSalesHistoryController;
use App\Http\Controllers\MercadoLibre\Reportes\getHistoryDispatchController;
use App\Http\Controllers\MercadoLibre\Reportes\getStockCriticController;
use App\Http\Controllers\MercadoLibre\Reportes\getUpcomingShipmentsController;
use App\Http\Controllers\MercadoLibre\Reportes\getDispatchEstimedLimitController;
use App\Http\Controllers\MercadoLibre\Reportes\getInformationDispatchDeliveredController;
use App\Http\Controllers\MercadoLibre\Reportes\getCancelledOrdersController;

// WAREHOUSES //
use App\Http\Controllers\Warehouses\warehouseListAllController;
use App\Http\Controllers\Warehouses\warehouseNewCompanyController;
use App\Http\Controllers\Warehouses\warehouseNewWarehouseStoreController;
use App\Http\Controllers\Warehouses\warehouseShowByIdController;
use App\Http\Controllers\Warehouses\warehouseUpdateCompanyNameController;
use App\Http\Controllers\Warehouses\warehouseUpdateDetailsController;
use App\Http\Controllers\Warehouses\warehouseDeleteCompanyByIdController;
use App\Http\Controllers\Warehouses\warehouseDeleteWarehouseByIdController;
use App\Http\Controllers\Warehouses\warehouseGetStockByWarehouseController;
use App\Http\Controllers\Warehouses\warehouseCreateProductStockWarehouseController;
use App\Http\Controllers\Warehouses\warehouseUpdateStockForWarehouseController;
use App\Http\Controllers\Warehouses\warehouseDeleteStockController;
use App\Http\Controllers\Warehouses\warehouseCompanyShowController;
use App\Http\Controllers\Warehouses\getCompareStockByProductiDController;
use App\Http\Controllers\Warehouses\getPriceNetoStockController;


// LOGIN //

use App\Http\Controllers\MercadoLibre\Login\loginController;
use App\Http\Controllers\MercadoLibre\Login\handleCallbackController;

// CONNECTIONS //

use App\Http\Controllers\MercadoLibre\Connections\testAndRefreshConnectionController;   
use App\Http\Controllers\MercadoLibre\Connections\ConexionTokenController;

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
use App\Http\Controllers\MercadoLibre\Products\itemController;
use App\Http\Controllers\MercadoLibre\Products\getStockController;
use App\Http\Controllers\MercadoLibre\Products\putProductoByUpdateController;
use App\Http\Controllers\MercadoLibre\Products\CreateProductController;

// SyncStatus //
use App\Http\Controllers\SyncStatusController;

// Public routes
Route::post('/login', [AuthController::class, 'login']); // Login user
Route::post('/users', [UserController::class, 'store']); // Create user

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']); 

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

    // WAREHOUSES (CRUD completo)
    Route::get("/warehouses-list", [warehouseListAllController::class, 'warehouse_list_all']);
    Route::get('/warehouses/{id}', [warehouseShowByIdController::class, 'warehouse_show']);        // Ver bodega específica
    Route::post('/warehouses', [warehouseNewWarehouseStoreController::class, 'warehouse_store']);           // Crear bodega
    Route::patch('/warehouses/{id}', [warehouseUpdateDetailsController::class, 'warehouse_update']);    // Actualizar bodega
    Route::delete('/warehouses/{id}', [warehouseDeleteWarehouseByIdController::class, 'warehouse_delete']);   // Eliminar bodega

    // Stock-specific routes
    Route::post('/warehouse-stock-create', [warehouseCreateProductStockWarehouseController::class, 'stock_store_by_url']);
    Route::put('/warehouse-stock/{id_mlc}', [warehouseUpdateStockForWarehouseController::class, 'stock_update']); // Actualizar por id_mlc
    Route::delete('/warehouse-stock/{id}', [warehouseDeleteStockController::class, 'stock_delete']); // Eliminar por ID
    Route::get('/warehouse/{warehouse_id}/stock', [warehouseGetStockByWarehouseController::class, 'getStockByWarehouse']); // Obtener stock por bodega

    //Stock Compare
    Route::get('/compare-stock/{id_mlc}/{idCompany}', [getCompareStockByProductiDController::class, 'getCompareStockByProductiD']); // Obtener stock por bodega
    Route::get('/price-neto-stock/{idCompany}', [getPriceNetoStockController::class, 'getPriceNetoStock']); // Obtener stock por bodega

    // Company-specific routes
    Route::post('/companies/{name}/{client_id}', [warehouseNewCompanyController::class, 'company_store_by_url']);
    Route::get('/companies/{id}', [warehouseCompanyShowController::class, 'company_show']);
    Route::patch('/companies/{id}', [warehouseUpdateCompanyNameController::class, 'company_update']);
    Route::delete('/companies/{id}', [warehouseDeleteCompanyByIdController::class, 'company_delete']);


    // Generate MerccadoLibre login Auth 2.0 URL
    Route::post('/mercadolibre/login', [loginController::class, 'login']);

    // Handle MercadoLibre callback
    Route::get('/mercadolibre/callback', [handleCallbackController::class, 'handleCallback']);

    // Check MercadoLibre connection status
    Route::get('/mercadolibre/test-connection/{client_id}', [testAndRefreshConnectionController::class, 'testAndRefreshConnection']);
    Route::get('/mercadolibre/conexionToken', [ConexionTokenController::class, 'index']);

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

    // Get stock sales history
    Route::get('/mercadolibre/stock-sales-history/{clientId}/{productId}', [getStockSalesHistoryController::class, 'getStockSalesHistory']);

    // Get dispach history

    // Save MercadoLibre products to database
    Route::get('/mercadolibre/save-products/{client_id}', [MercadoLibreProductController::class, 'saveProducts']);

    // Refresh MercadoLibre access token
    Route::post('/mercadolibre/refresh-token', [refreshAccessTokenController::class, 'refreshToken']);

    // REVIEWS
    Route::get('/reviews/{clientId}', [reviewController::class, 'getReviewsByClientId']);
    
    // ITEMS
    Route::post('/mercadolibre/items', [itemController::class, 'store']); // MercadoLibre items routes.
    Route::put('/mercadolibre/items/{item_id}', [itemController::class, 'update']); // Create and update items.

    // PRODUCT REPORT
    Route::get('/mercadolibre/client-item-list/{client_id}', [productReportController::class, 'listProductsByClientIdWithPaymentStatus']);

    // Stock Rotation
    Route::get('/mercadolibre/stock-rotation/{client_id}', [getStockRotationController::class, 'getStockRotation']);

    // Stock Reception
    Route::get('/mercadolibre/stock-reception/{client_id}', [getStockReceptionController::class, 'getStockReception']);

    // Available for Reception 
    Route::get('/mercadolibre/available-for-reception/{client_id}', [getAvailableForReceptionController::class, 'getAvailableForReception']); // PAUSADO

    // Products to Dispatch
    Route::get('/mercadolibre/products-to-dispatch/{client_id}', [getProductsToDispatchController::class, 'getProductsToDispatch']);
    
    Route::get('/mercadolibre/upcoming-shipments/{client_id}', [getUpcomingShipmentsController::class, 'getUpcomingShipments']);
    
    // Get stock of products
    Route::get('/mercadolibre/stock/{client_id}', [getStockController::class, 'getStock']);

    // Get Dispatch History
    Route::get('/mercadolibre/history-dispatch/{client_id}/{skuSearch}', [getHistoryDispatchController::class, 'getHistoryDispatch']);
    
    //Update stock
    Route::put('/mercadolibre/update-stock/{client_id}/{productId}', [putProductoByUpdateController::class, 'putProductoByUpdate']);

    // Get stock critic
    Route::get('/mercadolibre/stock-critic/{client_id}', [getStockCriticController::class, 'getStockCritic']);

    //Create product ML
    Route::post('/mercadolibre/Products/{client_id}/crear-producto', [CreateProductController::class, 'create']);

    // Get dispatch estimated limit
    Route::get('/mercadolibre/dispatch-estimated-limit/{client_id}', [getDispatchEstimedLimitController::class, 'getDispatchEstimedLimit']);

    //Get Information Dispatch Delivered
    Route::get('/mercadolibre/information-dispatch-delivered/{client_id}/{deliveredId}', [getInformationDispatchDeliveredController::class, 'getInformationDispatchDelivered']);

    Route::get('/mercadolibre/ordenes-canceladas/{clientId}', [getCancelledOrdersController::class, 'getCancelledOrders']);

});