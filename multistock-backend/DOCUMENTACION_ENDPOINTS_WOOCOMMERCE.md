# 📋 DOCUMENTACIÓN ENDPOINTS WOOCOMMERCE

## 🎯 **RESUMEN EJECUTIVO**

Esta documentación describe los endpoints implementados para la gestión de productos WooCommerce en múltiples tiendas, incluyendo exportación masiva, consulta y actualización por SKU.

---

## 📊 **1. EXPORTACIÓN DE PRODUCTOS A EXCEL**

### **Endpoint:** `GET /api/woocommerce/woo/export-all-products-all-stores`

**Descripción:** Exporta todos los productos de todas las tiendas WooCommerce a un archivo Excel con información detallada.

#### **Características:**
- ✅ **Exportación masiva** de productos de todas las tiendas
- ✅ **Optimización de rendimiento** (límites de tiempo y memoria aumentados)
- ✅ **23 columnas** de información detallada
- ✅ **Logging completo** del proceso
- ✅ **Manejo de errores** por tienda

#### **Configuración Técnica:**
```php
set_time_limit(600);        // 10 minutos
memory_limit('512M');       // 512 MB de memoria
maxProducts = 5000;         // Máximo 5000 productos
perPage = 100;             // 100 productos por página
```

#### **Columnas del Excel:**
| Columna | Descripción |
|---------|-------------|
| A | Tienda ID |
| B | Nombre Tienda |
| C | URL Tienda |
| D | Producto ID |
| E | Nombre Producto |
| F | Tipo |
| G | Estado |
| H | SKU |
| I | Precio |
| J | Precio Regular |
| K | Precio Oferta |
| L | En Oferta |
| M | Cantidad Stock |
| N | Estado Stock |
| O | Peso |
| P | Largo |
| Q | Ancho |
| R | Alto |
| S | Fecha Creación |
| T | Fecha Modificación |
| U | Categorías |
| V | Etiquetas |
| W | Permalink |

#### **Respuesta Exitosa:**
```json
{
  "message": "Archivo Excel generado correctamente.",
  "filename": "productos_todas_tiendas_2024-01-15.xlsx",
  "total_stores": 4,
  "total_products": 1250,
  "status": "success"
}
```

#### **Respuesta de Error:**
```json
{
  "message": "No hay tiendas registradas para exportar productos.",
  "status": "error"
}
```

---

## 🔍 **2. CONSULTA DE PRODUCTOS POR SKU**

### **Endpoint:** `GET /api/woocommerce/woo/products-by-sku`

**Descripción:** Busca un producto específico por SKU en todas las tiendas WooCommerce.

#### **Parámetros:**
| Parámetro | Tipo | Requerido | Descripción |
|-----------|------|-----------|-------------|
| `sku` | string | ✅ | SKU del producto a buscar |

#### **Ejemplo de Request:**
```bash
GET /api/woocommerce/woo/products-by-sku?sku=MLC1553228213
```

#### **Respuesta Exitosa:**
```json
{
  "message": "Búsqueda completada.",
  "sku": "MLC1553228213",
  "total_stores": 4,
  "stores_processed": 4,
  "products_found": 4,
  "products": [
    {
      "store_id": 1,
      "store_name": "Tienda Principal",
      "store_url": "https://tienda1.com",
      "product_id": 123,
      "name": "Producto Ejemplo",
      "sku": "MLC1553228213",
      "type": "simple",
      "status": "publish",
      "price": "22770",
      "regular_price": "22770",
      "sale_price": "",
      "stock_quantity": 50,
      "stock_status": "instock",
      "manage_stock": true,
      "date_created": "2024-01-15T10:30:00",
      "date_modified": "2024-01-15T15:45:00",
      "permalink": "https://tienda1.com/producto-ejemplo"
    }
  ],
  "errors": [],
  "status": "success"
}
```

#### **Respuesta de Error:**
```json
{
  "message": "Error de validación.",
  "errors": {
    "sku": ["El campo sku es obligatorio."]
  },
  "status": "error"
}
```

---

## ✏️ **3. ACTUALIZACIÓN DE PRODUCTOS POR SKU**

### **Endpoint:** `PUT /api/woocommerce/woo/products-by-sku`

**Descripción:** Actualiza la cantidad de stock y/o precio de un producto por SKU en todas las tiendas.

#### **Parámetros:**
| Parámetro | Tipo | Requerido | Descripción |
|-----------|------|-----------|-------------|
| `sku` | string | ✅ | SKU del producto a actualizar |
| `stock_quantity` | integer | ❌ | Nueva cantidad de stock |
| `regular_price` | numeric | ❌ | Nuevo precio regular |
| `sale_price` | numeric | ❌ | Nuevo precio de oferta |
| `stock_status` | string | ❌ | Estado del stock (instock/outofstock/onbackorder) |
| `manage_stock` | boolean | ❌ | Si se gestiona el stock |

#### **Ejemplo de Request:**
```json
{
  "sku": "MLC1553228213",
  "stock_quantity": 100,
  "regular_price": "22770",
  "sale_price": "22770",
  "stock_status": "instock",
  "manage_stock": true
}
```

#### **Respuesta Exitosa:**
```json
{
  "message": "Actualización completada.",
  "sku": "MLC1553228213",
  "update_data": {
    "sku": "MLC1553228213",
    "stock_quantity": 100,
    "regular_price": "22770",
    "sale_price": "22770",
    "stock_status": "instock",
    "manage_stock": true
  },
  "total_stores": 4,
  "stores_processed": 4,
  "total_products_updated": 4,
  "updated_products": [
    {
      "store_id": 1,
      "store_name": "Tienda Principal",
      "product_id": 123,
      "name": "Producto Ejemplo",
      "sku": "MLC1553228213",
      "updated_fields": ["stock_quantity", "regular_price", "sale_price"]
    }
  ],
  "errors": [],
  "status": "success"
}
```

#### **Respuesta de Error:**
```json
{
  "message": "Debes especificar al menos stock_quantity, regular_price o sale_price.",
  "status": "error"
}
```

---

## 🔧 **4. ENDPOINT DE DEBUGGING - LISTAR SKUS DISPONIBLES**

### **Endpoint:** `GET /api/woocommerce/woo/list-available-skus`

**Descripción:** Lista los SKUs disponibles en todas las tiendas para facilitar las pruebas y debugging.

#### **Características:**
- ✅ **Muestra los últimos 10 productos** de cada tienda
- ✅ **Información básica** de cada producto
- ✅ **Filtrado por SKU** (solo productos con SKU)
- ✅ **Ordenado por fecha** (más recientes primero)

#### **Respuesta Exitosa:**
```json
{
  "message": "Listado de SKUs completado.",
  "total_stores": 4,
  "stores_processed": 4,
  "stores_data": [
    {
      "store_id": 1,
      "store_name": "Tienda Principal",
      "store_url": "https://tienda1.com",
      "skus_count": 8,
      "skus": [
        {
          "product_id": 123,
          "name": "Producto Ejemplo",
          "sku": "MLC1553228213",
          "price": "22770",
          "stock_quantity": 50,
          "status": "publish"
        }
      ]
    }
  ],
  "errors": [],
  "status": "success"
}
```

---

## 📝 **5. RUTAS API COMPLETAS**

### **Archivo:** `routes/api.php`

```php
// Endpoints para productos por SKU en todas las tiendas
Route::get('/woocommerce/woo/products-by-sku', [\App\Http\Controllers\Woocommerce\WooProductController::class, 'getProductsBySkuAllStores']);
Route::put('/woocommerce/woo/products-by-sku', [\App\Http\Controllers\Woocommerce\WooProductController::class, 'updateProductsBySkuAllStores']);

// Endpoint de debugging para listar SKUs disponibles
Route::get('/woocommerce/woo/list-available-skus', [\App\Http\Controllers\Woocommerce\WooProductController::class, 'listAvailableSkus']);

// Endpoint de exportación masiva
Route::get('/woocommerce/woo/export-all-products-all-stores', [\App\Http\Controllers\Woocommerce\WooProductController::class, 'exportAllProductsFromAllStores']);
```

---

## 🔐 **6. AUTENTICACIÓN Y SEGURIDAD**

### **Middleware:**
- ✅ **`auth:sanctum`** - Autenticación requerida
- ✅ **Logging detallado** - Todas las operaciones se registran
- ✅ **Validación de datos** - Validación estricta de parámetros
- ✅ **Manejo de errores** - Respuestas de error estructuradas

### **Logs Generados:**
```php
Log::info('Iniciando exportación de productos', [...]);
Log::info('Procesando tienda', [...]);
Log::info('Búsqueda completada en tienda', [...]);
Log::info('Actualización completada', [...]);
```

---

## 🚀 **7. EJEMPLOS DE USO**

### **Flujo de Trabajo Típico:**

1. **Listar SKUs disponibles:**
   ```bash
   GET /api/woocommerce/woo/list-available-skus
   ```

2. **Consultar producto específico:**
   ```bash
   GET /api/woocommerce/woo/products-by-sku?sku=MLC1553228213
   ```

3. **Actualizar producto:**
   ```bash
   PUT /api/woocommerce/woo/products-by-sku
   {
     "sku": "MLC1553228213",
     "stock_quantity": 100,
     "regular_price": "22770"
   }
   ```

4. **Exportar todos los productos:**
   ```bash
   GET /api/woocommerce/woo/export-all-products-all-stores
   ```

---

## ⚠️ **8. CONSIDERACIONES IMPORTANTES**

### **Límites y Restricciones:**
- ⏱️ **Tiempo de ejecución:** Máximo 10 minutos para exportación
- 💾 **Memoria:** Máximo 512MB para exportación
- 📊 **Productos:** Máximo 5000 productos por exportación
- 🔍 **Búsqueda:** 100 productos por página en consultas

### **Optimizaciones Implementadas:**
- ✅ **Paginación** para evitar timeouts
- ✅ **Límites de memoria** aumentados
- ✅ **Logging optimizado** para debugging
- ✅ **Manejo de errores** por tienda individual

### **Casos de Uso:**
- 📈 **Gestión de inventario** centralizada
- 🔄 **Sincronización** entre múltiples tiendas
- 📊 **Reportes** de productos masivos
- ⚡ **Actualizaciones** rápidas por SKU

---

## 📞 **9. SOPORTE Y DEBUGGING**

### **Comandos de Debugging:**
```bash
# Ver logs de Laravel
tail -f storage/logs/laravel.log

# Verificar estado de tiendas
GET /api/woocommerce/woo-stores

# Listar SKUs para debugging
GET /api/woocommerce/woo/list-available-skus
```

### **Errores Comunes:**
1. **SKU no encontrado:** Verificar SKU con endpoint de debugging
2. **Timeout:** Reducir cantidad de productos o aumentar límites
3. **Error de conexión:** Verificar credenciales de tienda
4. **Validación fallida:** Revisar parámetros requeridos

---

## 📅 **10. HISTORIAL DE CAMBIOS**

### **Versión 1.0 (Actual):**
- ✅ Exportación masiva a Excel
- ✅ Consulta por SKU en todas las tiendas
- ✅ Actualización por SKU en todas las tiendas
- ✅ Endpoint de debugging para SKUs
- ✅ Optimizaciones de rendimiento
- ✅ Logging completo

### **Próximas Mejoras:**
- 🔄 Creación de productos en todas las tiendas
- 📊 Filtros avanzados para exportación
- 🔔 Notificaciones de cambios
- 📈 Métricas de rendimiento

---

**📧 Contacto:** Para soporte técnico o consultas sobre estos endpoints, revisar los logs de Laravel o contactar al equipo de desarrollo. 