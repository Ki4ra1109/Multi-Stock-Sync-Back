# Gestión de Productos WooCommerce y Bodegas

Este documento describe las funcionalidades implementadas para asignar productos de WooCommerce a bodegas y gestionar el stock.

## Funcionalidades Implementadas

### 1. Asignación de Productos WooCommerce a Bodegas

#### 1.1 Asignar Producto Existente a Bodega
**Endpoint:** `POST /api/woocommerce/woo/{storeId}/product/{productId}/assign-warehouse`

Asigna un producto existente de WooCommerce a una bodega específica.

**Parámetros de URL:**
- `storeId`: ID de la tienda WooCommerce
- `productId`: ID del producto en WooCommerce

**Body de la petición:**
```json
{
    "warehouse_id": 1,
    "available_quantity": 100,
    "price": 29.99,
    "condicion": "new",
    "currency_id": "CLP",
    "listing_type_id": "gold_special",
    "category_id": "MLB1234",
    "attribute": {
        "color": "rojo",
        "size": "M"
    },
    "pictures": [
        {"src": "https://example.com/image1.jpg"},
        {"src": "https://example.com/image2.jpg"}
    ],
    "sale_terms": [
        {"id": "WARRANTY_TYPE", "value_name": "Garantía del vendedor"}
    ],
    "shipping": {
        "mode": "me2",
        "free_shipping": true
    },
    "description": "Descripción del producto"
}
```

**Respuesta exitosa:**
```json
{
    "message": "Producto asignado correctamente a la bodega.",
    "stock_warehouse": {
        "id": 1,
        "id_mlc": "12345",
        "warehouse_id": 1,
        "title": "Producto Ejemplo",
        "price": 29.99,
        "available_quantity": 100,
        "condicion": "new",
        "currency_id": "CLP",
        "listing_type_id": "gold_special"
    },
    "woo_product": {
        "id": 12345,
        "name": "Producto Ejemplo",
        "type": "simple",
        "status": "publish",
        "price": "29.99"
    },
    "status": "success"
}
```

#### 1.2 Crear Producto y Asignar a Bodega
**Endpoint:** `POST /api/woocommerce/woo/{storeId}/product-create-assign-warehouse`

Crea un nuevo producto en WooCommerce y lo asigna a una bodega en una sola operación.

**Parámetros de URL:**
- `storeId`: ID de la tienda WooCommerce

**Body de la petición:**
```json
{
    "name": "Nuevo Producto",
    "type": "simple",
    "regular_price": "29.99",
    "description": "Descripción del producto",
    "short_description": "Descripción corta",
    "sku": "PROD-001",
    "categories": [
        {"id": 15}
    ],
    "images": [
        {"src": "https://example.com/image1.jpg"}
    ],
    "warehouse_id": 1,
    "warehouse_quantity": 100,
    "warehouse_price": 29.99,
    "condicion": "new",
    "currency_id": "CLP",
    "listing_type_id": "gold_special",
    "category_id": "MLB1234",
    "warehouse_attribute": {
        "color": "rojo",
        "size": "M"
    },
    "warehouse_pictures": [
        {"src": "https://example.com/image1.jpg"}
    ],
    "warehouse_sale_terms": [
        {"id": "WARRANTY_TYPE", "value_name": "Garantía del vendedor"}
    ],
    "warehouse_shipping": {
        "mode": "me2",
        "free_shipping": true
    },
    "warehouse_description": "Descripción para la bodega"
}
```

#### 1.3 Obtener Productos por Bodega
**Endpoint:** `GET /api/woocommerce/woo/{storeId}/warehouse/{warehouseId}/products`

Obtiene todos los productos de WooCommerce asignados a una bodega específica.

**Parámetros de URL:**
- `storeId`: ID de la tienda WooCommerce
- `warehouseId`: ID de la bodega

**Respuesta exitosa:**
```json
{
    "warehouse": {
        "id": 1,
        "name": "Bodega Principal",
        "location": "Santiago",
        "company": {
            "id": 1,
            "name": "Empresa Ejemplo"
        }
    },
    "products": [
        {
            "id": 12345,
            "name": "Producto Ejemplo",
            "type": "simple",
            "status": "publish",
            "price": "29.99",
            "stock_warehouse_id": 1,
            "warehouse_quantity": 100,
            "warehouse_price": 29.99,
            "warehouse_condicion": "new",
            "warehouse_currency_id": "CLP",
            "warehouse_listing_type_id": "gold_special"
        }
    ],
    "total_count": 1,
    "status": "success"
}
```

### 2. Gestión de Stock en Bodegas

#### 2.1 Obtener Todo el Stock de Bodegas
**Endpoint:** `GET /api/stock/warehouse`

Obtiene todo el stock de productos en todas las bodegas.

**Respuesta exitosa:**
```json
{
    "message": "Stock de bodegas obtenido correctamente",
    "warehouse_stock": [
        {
            "id": 1,
            "id_mlc": "12345",
            "warehouse_id": 1,
            "title": "Producto Ejemplo",
            "price": 29.99,
            "available_quantity": 100,
            "warehouse": {
                "id": 1,
                "name": "Bodega Principal",
                "company": {
                    "id": 1,
                    "name": "Empresa Ejemplo"
                }
            }
        }
    ],
    "status": "success"
}
```

#### 2.2 Obtener Stock de Bodega Específica
**Endpoint:** `GET /api/stock/warehouse/{warehouseId}`

Obtiene el stock de una bodega específica.

**Parámetros de URL:**
- `warehouseId`: ID de la bodega

#### 2.3 Crear Stock en Bodega
**Endpoint:** `POST /api/stock/warehouse`

Crea un nuevo registro de stock en una bodega.

**Body de la petición:**
```json
{
    "id_mlc": "12345",
    "warehouse_id": 1,
    "title": "Producto Ejemplo",
    "price": 29.99,
    "available_quantity": 100,
    "condicion": "new",
    "currency_id": "CLP",
    "listing_type_id": "gold_special",
    "category_id": "MLB1234",
    "attribute": {
        "color": "rojo",
        "size": "M"
    },
    "pictures": [
        {"src": "https://example.com/image1.jpg"}
    ],
    "sale_terms": [
        {"id": "WARRANTY_TYPE", "value_name": "Garantía del vendedor"}
    ],
    "shipping": {
        "mode": "me2",
        "free_shipping": true
    },
    "description": "Descripción del producto"
}
```

#### 2.4 Actualizar Stock en Bodega
**Endpoint:** `PUT /api/stock/warehouse/{stockId}`

Actualiza el stock de un producto en una bodega.

**Parámetros de URL:**
- `stockId`: ID del registro de stock

**Body de la petición:**
```json
{
    "available_quantity": 150,
    "price": 34.99,
    "condicion": "new",
    "currency_id": "CLP",
    "listing_type_id": "gold_special"
}
```

#### 2.5 Eliminar Stock de Bodega
**Endpoint:** `DELETE /api/stock/warehouse/{stockId}`

Elimina un registro de stock de una bodega.

**Parámetros de URL:**
- `stockId`: ID del registro de stock

#### 2.6 Obtener Stock por Empresa
**Endpoint:** `GET /api/stock/company/{companyId}`

Obtiene todo el stock de productos de una empresa específica.

**Parámetros de URL:**
- `companyId`: ID de la empresa

## Estructura de Datos

### Modelo StockWarehouse
```php
protected $fillable = [
    'id_mlc',           // ID del producto en WooCommerce/MercadoLibre
    'title',            // Título del producto
    'price',            // Precio del producto
    'condicion',        // Condición del producto (new, used, etc.)
    'currency_id',      // ID de la moneda
    'listing_type_id',  // ID del tipo de listado
    'available_quantity', // Cantidad disponible
    'warehouse_id',     // ID de la bodega
    'category_id',      // ID de la categoría
    'attribute',        // Atributos del producto (JSON)
    'pictures',         // Imágenes del producto (JSON)
    'sale_terms',       // Términos de venta (JSON)
    'shipping',         // Información de envío (JSON)
    'description',      // Descripción del producto
];
```

### Relaciones
- `StockWarehouse` pertenece a `Warehouse`
- `StockWarehouse` tiene muchos `ProductSale`
- `StockWarehouse` tiene una relación a través de `Warehouse` con `Company`

## Casos de Uso

### 1. Crear Producto en WooCommerce y Asignar a Bodega
1. Usar el endpoint `POST /api/woocommerce/woo/{storeId}/product-create-assign-warehouse`
2. Proporcionar datos del producto WooCommerce y datos de asignación a bodega
3. El sistema creará el producto en WooCommerce y lo registrará en la tabla `stock_warehouses`

### 2. Asignar Producto Existente a Bodega
1. Usar el endpoint `POST /api/woocommerce/woo/{storeId}/product/{productId}/assign-warehouse`
2. Proporcionar datos de asignación a bodega
3. El sistema registrará el producto existente en la tabla `stock_warehouses`

### 3. Gestionar Stock de Productos
1. Usar los endpoints del `StockController` para CRUD de stock
2. Actualizar cantidades, precios y otros datos según sea necesario
3. Consultar stock por bodega, empresa o globalmente

## Validaciones

### WooCommerce
- Validación de conexión a la tienda
- Validación de datos del producto según el tipo
- Validación de precios y cantidades

### Bodegas
- Validación de existencia de bodega
- Validación de datos de stock
- Validación de relaciones con empresas

### Stock
- Validación de cantidades mínimas
- Validación de precios positivos
- Validación de campos requeridos

## Logs y Monitoreo

Todos los endpoints incluyen logging detallado para:
- Inicio de operaciones
- Validaciones fallidas
- Errores de API
- Operaciones exitosas
- Información de debugging

## Manejo de Errores

Los endpoints manejan los siguientes tipos de errores:
- Errores de validación (422)
- Errores de WooCommerce API (400)
- Errores de base de datos (500)
- Recursos no encontrados (404)
- Errores de autenticación (401)

## Autenticación

Todos los endpoints requieren autenticación mediante Sanctum:
- Middleware: `auth:sanctum`
- Token de acceso requerido en headers
- Logging de usuario autenticado 