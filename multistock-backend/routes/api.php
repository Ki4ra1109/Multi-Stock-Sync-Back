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
//test
use App\Http\Controllers\MercadoLibre\Products\testingController;

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
use App\Http\Controllers\MercadoLibre\Reportes\ReviewController;
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
use App\Http\Controllers\MercadoLibre\Reportes\getProductSellerController;
use App\Http\Controllers\MercadoLibre\Reportes\getCompaniesProductsController;
use App\Http\Controllers\MercadoLibre\Reportes\getCancelledCompaniesController;


// Bodegas //
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

// Punto de venta //
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
use App\Http\Controllers\SalePoint\postDocumentSaleController;
use App\Http\Controllers\SalePoint\getDocumentByDownloadController;
use App\Http\Controllers\SalePoint\getAllHistorySaleIssueController;
use App\Http\Controllers\SalePoint\getAllHistorySaleFinishController;
use App\Http\Controllers\SalePoint\putSaleNoteController;
// LOGIN //

use App\Http\Controllers\MercadoLibre\Login\loginController;
use App\Http\Controllers\MercadoLibre\Login\handleCallbackController;

// Conexiones //

use App\Http\Controllers\MercadoLibre\Connections\testAndRefreshConnectionController;
use App\Http\Controllers\MercadoLibre\Connections\ConexionTokenController;

// Credenciales //

use App\Http\Controllers\MercadoLibre\Credentials\deleteCredentialsController;
use App\Http\Controllers\MercadoLibre\Credentials\getAllCredentialsDataController;
use App\Http\Controllers\MercadoLibre\Credentials\getCredentialsByClientIdController;
use App\Http\Controllers\MercadoLibre\Credentials\refreshAccessTokenController;

//  Productos  //

use App\Http\Controllers\MercadoLibre\Products\listProductByClientIdController;
use App\Http\Controllers\MercadoLibre\Products\searchProductsController;
use App\Http\Controllers\MercadoLibre\Products\getProductReviewsController;
use App\Http\Controllers\MercadoLibre\Products\saveProductsController;
use App\Http\Controllers\MercadoLibre\Products\itemController;
use App\Http\Controllers\MercadoLibre\Products\getStockController;
use App\Http\Controllers\MercadoLibre\Products\putProductoController;
use App\Http\Controllers\MercadoLibre\Products\CreateProductController;
use App\Http\Controllers\MercadoLibre\Products\CreateProductsMasiveController;
use App\Http\Controllers\MercadoLibre\Products\getCatalogProductController;
use App\Http\Controllers\MercadoLibre\Products\getCategoriaController;
use App\Http\Controllers\MercadoLibre\Products\getAtributosCategoriaController;
use App\Http\Controllers\MercadoLibre\Products\getSpecsDomainController;
use App\Http\Controllers\MercadoLibre\Products\getExcelCargaMasivaMLController;
use App\Http\Controllers\MercadoLibre\Products\getProductosExcelController;
//Tallas mercado libre

use App\Http\Controllers\MercadoLibre\Products\SizeGridController;
// woocommerce //
use App\Http\Controllers\Woocommerce\WooStoreController;
use App\Http\Controllers\Woocommerce\WooProductController;
use App\Http\Controllers\Woocommerce\WooCategoryController;
// SyncStatus //
use App\Http\Controllers\SyncStatusController;
use Dotenv\Repository\Adapter\PutenvAdapter;

// Rol //
use App\Http\Controllers\RolController;



// Rutas públicas
Route::post('/login', [AuthController::class, 'login']); // Iniciar sesión de usuario
Route::post('/users', [UserController::class, 'store']); // Crear usuario
Route::get('/users/{id}', [UserController::class, 'show']);

// Rutas protegidas
Route::middleware('auth:sanctum')->group(function () {
Route::post('/logout', [AuthController::class, 'logout']); // Cerrar sesión

Route::post('/user/change-password', [AuthController::class, 'changePassword'])->middleware('auth:sanctum');//cambiar contraseña

// Asignar roles
Route::put('/users/{id}/asignar-rol', [UserController::class, 'asignarRol'])->middleware(['auth:sanctum', 'role:admin,RRHH']);

Route::middleware('auth:sanctum')->get('/user/profile', function (Request $request) {
    return response()->json(['user' => $request->user()]);
});

Route::middleware(['auth:sanctum', 'role:admin,finanzas'])->group(function () {
    Route::get('/history-sale/{client_id}', [getHistorySaleController::class, 'getHistorySale']);
});
    // USUARIOS
    Route::get('/users', [UserController::class, 'usersList']); // Obtener lista de usuarios
    Route::get('/users/{id}', [UserController::class,'show']); // Obtener usuario específico
    Route::patch('/users/{id}', [UserController::class,'update']); // Actualizar usuario
    Route::delete('/users/{id}', [UserController::class,'delete']); // Eliminar usuario



    // ROL
    Route::get('/roles', [RolController::class, 'index']); // Obtener todos los roles
    Route::post('/roles/nuevo', [RolController::class, 'store']); // Crear rol
    Route::delete('/roles/{id}', [RolController::class, 'destroy']); // Eliminar rol
    Route::put('/roles/{id}', [RolController::class, 'update']); // Actualizar rol

    // SINCRONIZACIÓN
    Route::post('/sincronizar', [SyncStatusController::class, 'iniciarSincronizacion']); // Iniciar sincronización
    Route::get('/estado-sincronizacion', [SyncStatusController::class, 'estadoSincronizacion']); // Estado de sincronización

    // CRUD Clientes
    Route::get('/clientes', [ClientesController::class, 'index']); // Obtener todos los clientes
    Route::post('/clientes', [ClientesController::class, 'store']); // Crear cliente
    Route::get('/clientes/{id}', [ClientesController::class, 'show']); // Obtener cliente específico
    Route::patch('/clientes/{id}', [ClientesController::class, 'update']); // Actualizar cliente
    Route::delete('/clientes/{id}', [ClientesController::class, 'destroy']); // Eliminar cliente

    // BODEGAS (CRUD completo)
    Route::get("/warehouses-list", [warehouseListAllController::class, 'warehouse_list_all']);
    Route::get('/warehouses/{id}', [warehouseShowByIdController::class, 'warehouse_show']); // Ver bodega específica
    Route::post('/warehouses', [warehouseNewWarehouseStoreController::class, 'warehouse_store']); // Crear bodega
    Route::patch('/warehouses/{id}', [warehouseUpdateDetailsController::class, 'warehouse_update']); // Actualizar bodega
    Route::delete('/warehouses/{id}', [warehouseDeleteWarehouseByIdController::class, 'warehouse_delete']); // Eliminar bodega
    Route::get('/warehouses-by-company/{clientId}', [getWarehouseByCompanyIdController::class, 'getWarehouseByCompany']); // Obtener bodegas por empresa

    // STOCK
    Route::post('/warehouse-stock-create', [warehouseCreateProductStockWarehouseController::class, 'stock_store_by_url']); // Crear stock
    Route::put('/warehouse-stock/{id_mlc}', [warehouseUpdateStockForWarehouseController::class, 'stock_update']); // Actualizar stock por ID_MLC
    Route::delete('/warehouse-stock/{id}', [warehouseDeleteStockController::class, 'stock_delete']); // Eliminar stock
    Route::get('/warehouse/{warehouse_id}/stock', [warehouseGetStockByWarehouseController::class, 'getStockByWarehouse']); // Obtener stock por bodega
    Route::post('/warehouse-stock-masive/{warehouseId}', [warehouseCreateMasiveProductStockController::class, 'warehouseCreateMasiveProductStock']); // Crear stock masivo

    // COMPARACIÓN DE STOCK
    Route::get('/compare-stock/{id_mlc}/{idCompany}', [getCompareStockByProductiDController::class, 'getCompareStockByProductiD']);
    Route::get('/price-neto-stock/{idCompany}', [getPriceNetoStockController::class, 'getPriceNetoStock']);

    // EMPRESAS
    Route::post('/companies/{name}/{client_id}', [warehouseNewCompanyController::class, 'company_store_by_url']); // Crear empresa
    Route::get('/companies/{id}', [warehouseCompanyShowController::class, 'company_show']); // Obtener empresa
    Route::patch('/companies/{id}', [warehouseUpdateCompanyNameController::class, 'company_update']); // Actualizar empresa
    Route::delete('/companies/{id}', [warehouseDeleteCompanyByIdController::class, 'company_delete']); // Eliminar empresa

    // LOGIN MERCADO LIBRE
    Route::get('/mercadolibre/callback', [handleCallbackController::class, 'handleCallback']); // Callback de MercadoLibre
    Route::post('/mercadolibre/login', [loginController::class, 'login']); // Generar URL de autenticación OAuth 2.0

    // CONEXIONES MERCADO LIBRE
    Route::get('/mercadolibre/test-connection/{client_id}', [testAndRefreshConnectionController::class, 'testAndRefreshConnection']); // Testear conexión
    Route::get('/mercadolibre/conexionToken', [ConexionTokenController::class, 'index']); // Mostrar tokens

    // CREDENCIALES MERCADO LIBRE
    Route::get('/mercadolibre/credentials', [getAllCredentialsDataController::class, 'getAllCredentialsData']); // Obtener todas las credenciales
    Route::get('/mercadolibre/credentials/{client_id}', [getCredentialsByClientIdController::class, 'getCredentialsByClientId']); // Obtener credencial por client_id
    Route::delete('/mercadolibre/credentials/{client_id}', [deleteCredentialsController::class, 'deleteCredentials']); // Eliminar credencial por client_id

    // PRODUCTOS MERCADO LIBRE
    Route::get('/mercadolibre/products/{client_id}', [listProductByClientIdController::class, 'listProductsByClientId']); // Listar productos por client_id
    Route::get('mercadolibre/categoria/{id}/atributos', [getAtributosCategoriaController::class, 'getAtributos']); // Obtener atributos por categoría
    Route::get('/mercadolibre/products/{client_id}/catalogo', [getCatalogProductController::class, 'getCatalogProducts']); // Obtener productos del catálogo
    Route::get('mercadolibre/categoria/{id}', [getCategoriaController::class, 'getCategoria']); // Obtener categoría
    Route::get('mercadolibre/specs/{id}', [getSpecsDomainController::class, 'getSpecs']); // Obtener especificaciones técnicas
    Route::get('/mercadolibre/stock/{client_id}', [getStockController::class, 'getStock']); // Obtener stock de productos
    Route::get('/mercadolibre/save-products/{client_id}', [saveProductsController::class, 'saveProducts']); // Guardar productos
    Route::get('/mercadolibre/products/search/{client_id}', [searchProductsController::class, 'searchProducts']); // Buscar productos
    Route::get('/mercadolibre/categorias/{client_id}',[CreateProductsMasiveController::class,'ListCategory']);//lista de categorias por compañia

    // CREACIÓN Y MODIFICACIÓN DE PRODUCTOS
    Route::post('/mercadolibre/Products/{client_id}/crear-producto', [CreateProductController::class, 'create']); // Crear producto
    Route::post('/mercadolibre/items', [itemController::class, 'store']); // Crear ítem
    Route::put('/mercadolibre/items/{item_id}', [itemController::class, 'update']); // Actualizar ítem
    Route::put('/mercadolibre/update-stock/{client_id}/{productId}', [putProductoByUpdateController::class, 'putProductoByUpdate']); // Actualizar stock
    Route::get('/mercadolibre/carga-masiva', [getExcelCargaMasivaMLController::class, 'redirigir']); // Redirigir a carga masiva
    Route::post('/mercadolibre/carga-masiva/leer-excel', [getProductosExcelController::class, 'leerExcel']); // Leer carga masiva
    Route::put('/mercadolibre/update/{client_id}/{productId}', [putProductoByUpdateController::class, 'putProductoByUpdate']); // Actualizar stock
    Route::get('/mercadolibre/carga-masiva/descargar-platilla/{client_id}/{categoryId}', [CreateProductsMasiveController::class, 'downloadTemplate']); // Leer carga masiva
    Route::get('/mercadolibre/size-guides/{client_id}', [CreateProductController::class, 'getSizeGuides']);
    // CREACION, MODIFICACION Y LISTADO DE TALLAS MERCADO LIBRE
    Route::get('/mercadolibre/sizeGrids/{client_id}', [SizeGridController::class, 'listSizeGrids']); // listar tallas
    Route::post('/mercadolibre/sizeGrids/{client_id}', [SizeGridController::class, 'createSizeGrid']); // crear talla
    Route::get('/mercadolibre/sizeGrids/{client_id}/{sizeGridId}', [SizeGridController::class, 'showSizeGrid']); // Mostrar talla
    Route::delete('/mercadolibre/sizeGrids/{client_id}/{sizeGridId}', [SizeGridController::class, 'deleteSizeGrid']); // Eliminar talla
    Route::put('/mercadolibre/sizeGrids/{client_id}/{sizeGridId}', [SizeGridController::class, 'updateSizeGrid']); // Actualizar talla
    Route::get('/mercadolibre/domainID/{client_id}',[SizeGridController::class, 'getAvailableDomains']);//lista de dominios
    Route::get('/mercadolibre/domainID/{domain_id}/{client_id}',[SizeGridController::class, 'getDomain']);
    // REPORTES MERCADO LIBRE
    Route::get('/mercadolibre/annual-sales/{client_id}', [getAnnualSalesController::class, 'getAnnualSales']); // Ventas anuales
    Route::get('/mercadolibre/available-for-reception/{client_id}', [getAvailableForReceptionController::class, 'getAvailableForReception']); // Disponible para recepción
    Route::get('/mercadolibre/compare-annual-sales-data/{client_id}', [compareAnnualSalesDataController::class, 'compareAnnualSalesData']); // Comparar ventas anuales
    Route::get('/mercadolibre/compare-sales-data/{client_id}', [compareSalesDataController::class, 'compareSalesData']); // Comparar ventas mensuales
    Route::get('/mercadolibre/client-item-list/{client_id}', [productReportController::class, 'listProductsByClientIdWithPaymentStatus']); // Reporte de productos
    Route::get('/mercadolibre/daily-sales/{client_id}', [getDailySalesController::class, 'getDailySales']); // Ventas diarias
    Route::get('/mercadolibre/dispatch-estimated-limit/{client_id}', [getDispatchEstimedLimitController::class, 'getDispatchEstimedLimit']); // Límite estimado de despacho
    Route::get('/mercadolibre/history-dispatch/{client_id}/{skuSearch}', [getHistoryDispatchController::class, 'getHistoryDispatch']); // Historial de despachos
    Route::get('/mercadolibre/information-dispatch-delivered/{client_id}/{deliveredId}', [getInformationDispatchDeliveredController::class, 'getInformationDispatchDelivered']); // Información de despacho entregado
    Route::get('/mercadolibre/invoices/{client_id}', [getInvoiceReportController::class, 'getInvoiceReport']); // Reporte de facturas
    Route::get('/mercadolibre/order-statuses/{client_id}', [getOrderStatusesController::class, 'getOrderStatuses']); // Estados de orden
    Route::get('/mercadolibre/ordenes-canceladas/{clientId}', [getCancelledOrdersController::class, 'getCancelledOrders']); // Órdenes canceladas
    Route::get('/mercadolibre/products-to-dispatch/{client_id}', [getProductsToDispatchController::class, 'getProductsToDispatch']); // Productos por despachar
    Route::get('/mercadolibre/refunds-by-category/{client_id}', [getRefundsByCategoryController::class, 'getRefundsByCategory']); // Reembolsos por categoría
    Route::get('/mercadolibre/sales-by-date-range/{client_id}', [getSalesByDateRangeController::class, 'getSalesByDateRange']); // Ventas por rango de fecha
    Route::get('/mercadolibre/sales-by-month/{client_id}', [getSalesByMonthController::class, 'getSalesByMonth']); // Ventas mensuales
    Route::get('/mercadolibre/sales-by-week/{client_id}', [getSalesByWeekController::class, 'getSalesByWeek']); // Ventas por semana
    Route::get('/mercadolibre/stock-critic/{client_id}', [getStockCriticController::class, 'getStockCritic']); // Stock crítico
    Route::get('/mercadolibre/stock-reception/{client_id}', [getStockReceptionController::class, 'getStockReception']); // Recepción de stock
    Route::get('/mercadolibre/stock-rotation/{client_id}', [getStockRotationController::class, 'getStockRotation']); // Rotación de stock
    Route::get('/mercadolibre/stock-sales-history/{clientId}/{productId}', [getStockSalesHistoryController::class, 'getStockSalesHistory']); // Historial de ventas por producto
    Route::get('/mercadolibre/summary/{client_id}', [summaryController::class, 'summary']); // Resumen
    Route::get('/mercadolibre/top-payment-methods/{client_id}', [getTopPaymentMethodsController::class, 'getTopPaymentMethods']); // Métodos de pago principales
    Route::get('/mercadolibre/top-selling-products/{client_id}', [getTopSellingProductsController::class, 'getTopSellingProducts']); // Productos más vendidos
    Route::get('/mercadolibre/upcoming-shipments/{client_id}', [getUpcomingShipmentsController::class, 'getUpcomingShipments']); // Próximos envíos
    Route::get('/mercadolibre/weeks-of-month', [getWeeksOfMonthController::class, 'getWeeksOfMonth']); // Semanas del mes
    Route::get('/reviews/{clientId}', [reviewController::class, 'getReviewsByClientId']); // Reseñas por cliente
    Route::get('/mercadolibre/products/reviews/{product_id}', [getProductReviewsController::class, 'getProductReviews']); // Reseñas por producto
    Route::get('/mercadolibre/all-products/{client_id}', [getProductSellerController::class, 'getProductSeller']); // Obtener todos los productos por client_id
    Route::get('/mercadolibre/cancelled-products', [getCancelledCompaniesController::class, 'getCancelledProductsAllCompanies']); // Obtener productos cancelados de las 4 empresas
    Route::get('/mercadolibre/get-total-sales-all-companies', [getCompaniesProductsController::class, 'getTotalSalesAllCompanies']);//Obtener total de todos los productos vendidos

    // Refrescar token de MercadoLibre
    Route::post('/mercadolibre/refresh-token', [refreshAccessTokenController::class, 'refreshToken']);

    // PUNTO DE VENTA
    Route::get('/clients-all', [clientAllListController::class, 'clientAllList']); // Obtener todos los clientes
    Route::get('/history-sale/{client_id}', [getHistorySaleController::class, 'getHistorySale']); // Obtener historial de ventas
    Route::get('/history-sale-pendient/{client_id}', [getHistoryPendientController::class, 'getHistoryPendient']); // Obtener historial pendiente
    Route::get('/products-by-company/{idCompany}', [getProductByCompanyIdController::class, 'getProductByCompanyId']); // Productos por empresa
    Route::get('/search-sale-by-folio/{companyId}', [getSearchSaleByFolioController::class, 'getSearchSaleByFolio']); // Buscar venta por folio
    Route::get('/document-sale/{client_id}/{id_folio}', [getDocumentByDownloadController::class, 'getDocumentByDownload']); // Descargar documento de venta
    Route::get('/history-sale-issue/{client_id}', [getAllHistorySaleIssueController::class, 'getAllHistorySaleIssue']); // Obtener historial de ventas emitidas
    Route::get('/history-sale-finish/{client_id}', [getAllHistorySaleFinishController::class, 'getAllHistorySaleFinish']); // Obtener historial de ventas finalizadas

    Route::post('/create-new-client', [createNewClientController::class, 'createNewClient']); // Crear cliente
    Route::post('/generated-sale-note/{status}', [generatedSaleNoteController::class, 'generatedSaleNote']); // Generar nota de venta
    Route::post('/document-sale', [postDocumentSaleController::class, 'postDocumentSale']); // Subir documento de venta

    Route::delete('/delete-history-sale/{companyId}/{saleId}', [getDeleteHistoryByIdSaleController::class, 'getDeleteHistoryByIdSale']); // Eliminar historial por ID
    Route::patch('/generated-sale-note/{saleId}/{status}', [PutSaleNoteController::class, 'putSaleNote']); // Actualizar estado de venta
    Route::patch('/sale-note-patch/{saleId}/{status}', [getHistorySalePatchStatusController::class, 'getHistorySalePatchStatus']); // Actualizar estado de venta
    Route::put('/sale-note/{companyId}/{folio}', [putSaleNoteByFolioController::class, 'putSaleNoteByFolio']); // Actualizar nota de venta por folio
    //test
    Route::get('/test/{clientId}', [testingController::class, 'testingGet']); //para probar endpoint de mercadolibre de forma directa
    Route::post('/test/{clientId}', [testingController::class, 'testingPost']);//test cn post
    // WooCommerce
    Route::get('/woocommerce/woo/{storeId}/products', [WooStoreController::class, 'getProductsWooCommerce']); // Obtener productos de WooCommerce
    Route::post('/woocommerce/woo-stores', [WooStoreController::class, 'storeWoocommerce']); // Registrar tienda WooCommerce
    Route::put('/woocommerce/woo/{storeId}/product/{productId}', [WooProductController::class, 'updateProduct']);
    Route::post('/woocommerce/woo/{storeId}/product', [WooProductController::class, 'createProduct']);
    Route::delete('/woocommerce/woo/{storeId}/product/{productId}', [WooProductController::class, 'deleteProduct']);
    Route::get('/woocommerce/woo/{storeId}/product/{productId}', [WooProductController::class, 'getProduct']);
    //categorias wooComerce
    Route::Get('/woocommerce/woo/{storeId}/categories', [WooCategoryController::class, 'listCategories']);
    Route::Get('/woocommerce/woo/{storeId}/category/{categoryId}',[WooCategoryController::class, 'getCategory']);
    Route::Post('/woocomerce/{storeId}/category', [WooCategoryController::class, 'createCategory']);
   });

