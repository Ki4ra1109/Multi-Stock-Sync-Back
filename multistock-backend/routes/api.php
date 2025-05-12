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
use App\Http\Controllers\Warehouses\getWarehouseByCompanyIdController;
use App\Http\Controllers\Warehouses\warehouseCreateMasiveProductStockController;

// SalePoint //
use App\Http\Controllers\SalePoint\createNewClientController;
use App\Http\Controllers\SalePoint\clientAllListController;
use App\Http\Controllers\SalePoint\getProductByCompanyIdController;
use App\Http\Controllers\SalePoint\generatedSaleNoteController;
use App\Http\Controllers\SalePoint\getHistorySaleController;
use App\Http\Controllers\SalePoint\getHistoryPendientController;
use App\Http\Controllers\SalePoint\getHistorySalePatchStatusController;
use App\Http\Controllers\SalePoint\getDeleteHistoryByIdSaleController;
use App\Http\Controllers\SalePoint\getSearchSaleByFolioController;
use App\Http\Controllers\SalePoint\putSaleNoteByFolioController;

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
use App\Http\Controllers\MercadoLibre\Products\getCatalogProductController;
use App\Http\Controllers\MercadoLibre\Products\getCategoriaController;
use App\Http\Controllers\MercadoLibre\Products\getAtributosCategoriaController;
use App\Http\Controllers\MercadoLibre\Products\getSpecsDomainController;

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
    Route::get('/warehouses-by-company/{clientId}', [getWarehouseByCompanyIdController::class, 'getWarehouseByCompany']); // Obtener bodegas por empresa

    // Stock-specific routes
    Route::post('/warehouse-stock-create', [warehouseCreateProductStockWarehouseController::class, 'stock_store_by_url']);
    Route::put('/warehouse-stock/{id_mlc}', [warehouseUpdateStockForWarehouseController::class, 'stock_update']); // Actualizar por id_mlc
    Route::delete('/warehouse-stock/{id}', [warehouseDeleteStockController::class, 'stock_delete']); // Eliminar por ID
    Route::get('/warehouse/{warehouse_id}/stock', [warehouseGetStockByWarehouseController::class, 'getStockByWarehouse']); // Obtener stock por bodega
    Route::post('/warehouse-stock-masive/{warehouseId}', [warehouseCreateMasiveProductStockController::class, 'warehouseCreateMasiveProductStock']); // Crear stock masivo

    //Stock Compare
    Route::get('/compare-stock/{id_mlc}/{idCompany}', [getCompareStockByProductiDController::class, 'getCompareStockByProductiD']); // Obtener stock por bodega
    Route::get('/price-neto-stock/{idCompany}', [getPriceNetoStockController::class, 'getPriceNetoStock']); // Obtener stock por bodega

    // Company-specific routes
    Route::post('/companies/{name}/{client_id}', [warehouseNewCompanyController::class, 'company_store_by_url']);
    Route::get('/companies/{id}', [warehouseCompanyShowController::class, 'company_show']);
    Route::patch('/companies/{id}', [warehouseUpdateCompanyNameController::class, 'company_update']);
    Route::delete('/companies/{id}', [warehouseDeleteCompanyByIdController::class, 'company_delete']);

    //Login
    Route::get('/mercadolibre/callback', [handleCallbackController::class, 'handleCallback']); // Handle MercadoLibre callback
    Route::post('/mercadolibre/login', [loginController::class, 'login']);// Generate MerccadoLibre login Auth 2.0 URL

    // Check MercadoLibre connection status
    Route::get('/mercadolibre/test-connection/{client_id}', [testAndRefreshConnectionController::class, 'testAndRefreshConnection']);
    Route::get('/mercadolibre/conexionToken', [ConexionTokenController::class, 'index']);

    // Mercadolibre Credentials CRUD
    Route::get('/mercadolibre/credentials', [getAllCredentialsDataController::class, 'getAllCredentialsData']); // Get MercadoLibre credentials if are saved in db
    Route::get('/mercadolibre/credentials/{client_id}', [getCredentialsByClientIdController::class, 'getCredentialsByClientId']); // Get MercadoLibre credentials by client_id
    Route::delete('/mercadolibre/credentials/{client_id}', [deleteCredentialsController::class, 'deleteCredentials']); // Delete credentials using client_id

    //Mercadolibre Products
    Route::get('/mercadolibre/products/{client_id}', [listProductByClientIdController::class, 'listProductsByClientId']);// Get MercadoLibre products list by client_id
    Route::get('mercadolibre/categoria/{id}/atributos', [getAtributosCategoriaController::class, 'getAtributos']); // Get attributes by category ID
    Route::get('/mercadolibre/products/{client_id}/catalogo', [getCatalogProductController::class, 'getCatalogProducts']); // Get catalog products
    Route::get('mercadolibre/categoria/{id}', [getCategoriaController::class, 'getCategoria']); // Get category by ID
    Route::get('mercadolibre/specs/{id}', [getSpecsDomainController::class, 'getSpecs']); // Get technical specifications by domain ID
    Route::get('/mercadolibre/stock/{client_id}', [getStockController::class, 'getStock']); // Get stock of products
    Route::get('/mercadolibre/save-products/{client_id}', [saveProductsController::class, 'saveProducts']); // Get saves products
    Route::get('/mercadolibre/products/search/{client_id}', [searchProductsController::class, 'searchProducts']); // Search MercadoLibre products by client_id and search term

    // Mercadolibre Products post, put 
    Route::post('/mercadolibre/Products/{client_id}/crear-producto', [CreateProductController::class, 'create']); //Create product ML
    Route::post('/mercadolibre/items', [itemController::class, 'store']); // MercadoLibre items routes.
    Route::put('/mercadolibre/items/{item_id}', [itemController::class, 'update']); // Create and update items.
    Route::put('/mercadolibre/update-stock/{client_id}/{productId}', [putProductoByUpdateController::class, 'putProductoByUpdate']);//Update stock

    // Mercadolibre Reports
    Route::get('/mercadolibre/annual-sales/{client_id}', [getAnnualSalesController::class, 'getAnnualSales']);// Get MercadoLibre annual sales by client_id
    Route::get('/mercadolibre/available-for-reception/{client_id}', [getAvailableForReceptionController::class, 'getAvailableForReception']); // Available for Reception 
    Route::get('/mercadolibre/compare-annual-sales-data/{client_id}', [compareAnnualSalesDataController::class, 'compareAnnualSalesData']);// Compare sales data between two years
    Route::get('/mercadolibre/compare-sales-data/{client_id}', [compareSalesDataController::class, 'compareSalesData']); // Compare sales data between two months
    Route::get('/mercadolibre/client-item-list/{client_id}', [productReportController::class, 'listProductsByClientIdWithPaymentStatus']); // PRODUCT REPORT

    Route::get('/mercadolibre/daily-sales/{client_id}', [getDailySalesController::class, 'getDailySales']); // Get daily sales by client_id
    Route::get('/mercadolibre/dispatch-estimated-limit/{client_id}', [getDispatchEstimedLimitController::class, 'getDispatchEstimedLimit']); // Get dispatch estimated limit
    Route::get('/mercadolibre/history-dispatch/{client_id}/{skuSearch}', [getHistoryDispatchController::class, 'getHistoryDispatch']);// Get Dispatch History
    Route::get('/mercadolibre/information-dispatch-delivered/{client_id}/{deliveredId}', [getInformationDispatchDeliveredController::class, 'getInformationDispatchDelivered']);//Get Information Dispatch Delivered
    Route::get('/mercadolibre/invoices/{client_id}', [getInvoiceReportController::class, 'getInvoiceReport']); // Get MercadoLibre invoice report by client_id
    
    Route::get('/mercadolibre/order-statuses/{client_id}', [getOrderStatusesController::class, 'getOrderStatuses']); // Get order statuses
    Route::get('/mercadolibre/ordenes-canceladas/{clientId}', [getCancelledOrdersController::class, 'getCancelledOrders']); // Get cancelled orders by client_id
    Route::get('/mercadolibre/products-to-dispatch/{client_id}', [getProductsToDispatchController::class, 'getProductsToDispatch']); // Products to Dispatch
    Route::get('/mercadolibre/refunds-by-category/{client_id}', [getRefundsByCategoryController::class, 'getRefundsByCategory']); // Get refunds or returns by category
    
    Route::get('/mercadolibre/sales-by-date-range/{client_id}', [getSalesByDateRangeController::class, 'getSalesByDateRange']); // Get sales by date range
    Route::get('/mercadolibre/sales-by-month/{client_id}', [getSalesByMonthController::class, 'getSalesByMonth']); // Get MercadoLibre sales by month by client_id
    Route::get('/mercadolibre/sales-by-week/{client_id}', [getSalesByWeekController::class, 'getSalesByWeek']); // Get total sales for a specific week
    Route::get('/mercadolibre/stock-critic/{client_id}', [getStockCriticController::class, 'getStockCritic']);// Get stock critic
    Route::get('/mercadolibre/stock-reception/{client_id}', [getStockReceptionController::class, 'getStockReception']); // Stock Reception
    Route::get('/mercadolibre/stock-rotation/{client_id}', [getStockRotationController::class, 'getStockRotation']); // Stock Rotation
    Route::get('/mercadolibre/stock-sales-history/{clientId}/{productId}', [getStockSalesHistoryController::class, 'getStockSalesHistory']);// Get stock sales history
    Route::get('/mercadolibre/summary/{client_id}', [summaryController::class, 'summary']);// Get summary
    
    Route::get('/mercadolibre/top-payment-methods/{client_id}', [getTopPaymentMethodsController::class, 'getTopPaymentMethods']); // Get top payment methods
    Route::get('/mercadolibre/top-selling-products/{client_id}', [getTopSellingProductsController::class, 'getTopSellingProducts']); // Get top selling products
    Route::get('/mercadolibre/upcoming-shipments/{client_id}', [getUpcomingShipmentsController::class, 'getUpcomingShipments']); // Get upcoming shipments
    Route::get('/mercadolibre/weeks-of-month', [getWeeksOfMonthController::class, 'getWeeksOfMonth']); // Get weeks of the month
    Route::get('/reviews/{clientId}', [reviewController::class, 'getReviewsByClientId']); // MercadoLibre reviews by client_id

    // Get product reviews by product_id
    Route::get('/mercadolibre/products/reviews/{product_id}', [getProductReviewsController::class, 'getProductReviews']);

    // Mercadolibre Credentials
    Route::get('/mercadolibre/save-products/{client_id}', [MercadoLibreProductController::class, 'saveProducts']);// Save MercadoLibre products to database
    Route::post('/mercadolibre/refresh-token', [refreshAccessTokenController::class, 'refreshToken']);// Refresh MercadoLibre access token

    //SalePoint get
    Route::get('/clients-all', [clientAllListController::class, 'clientAllList']);// Get all clients
    Route::get('/history-sale/{client_id}', [getHistorySaleController::class, 'getHistorySale']); //Get history sale
    Route::get('/history-sale-pendient/{client_id}', [getHistoryPendientController::class, 'getHistoryPendient']); //Get history sale Pendient
    Route::get('/products-by-company/{idCompany}', [getProductByCompanyIdController::class, 'getProductByCompanyId']);// Get product by company ID
    Route::get('/search-sale-by-folio/{companyId}', [getSearchSaleByFolioController::class, 'getSearchSaleByFolio']); //Get search sale by folio

    //SalePoint Post
    Route::post('/create-new-client', [createNewClientController::class, 'createNewClient']);//Create a new client
    Route::post('/generated-sale-note/{status}', [generatedSaleNoteController::class, 'generatedSaleNote']); // Generate sale note

    //Sale Point patch,delete,put
    Route::delete('/delete-history-sale/{companyId}/{saleId}', [getDeleteHistoryByIdSaleController::class, 'getDeleteHistoryByIdSale']); //Get Delete history by id sale
    Route::patch('/generated-sale-note/{saleId}/{status}', [getHistorySalePatchStatusController::class, 'getHistorySalePatchStatus']); //Get history sale Patch Status
    Route::put('/sale-note/{companyId}/{folio}', [putSaleNoteByFolioController::class, 'putSaleNoteByFolio']); //put sale note by folio

});