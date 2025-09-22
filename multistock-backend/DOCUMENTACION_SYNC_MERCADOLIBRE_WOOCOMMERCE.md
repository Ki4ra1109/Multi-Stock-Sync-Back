# Documentación - Sincronización MercadoLibre con WooCommerce

## Endpoints de Sincronización

### 1. Sincronización Síncrona
### POST `/api/mercadolibre/products/{client_id}/sync-woocommerce`

### 2. Sincronización Asíncrona (Recomendada para grandes volúmenes)
### POST `/api/mercadolibre/products/{client_id}/sync-woocommerce-async`

Sincroniza productos de MercadoLibre con WooCommerce basándose en el campo "model" como SKU.

#### Parámetros de URL
- `client_id` (requerido): ID del cliente de MercadoLibre

#### Parámetros de Query (opcionales)
- `mode` (opcional): Modo de sincronización
  - `sync` (default): Sincroniza y crea productos
  - `update`: Solo actualiza productos existentes
  - `create`: Solo crea productos nuevos
- `store_ids` (opcional): IDs específicos de tiendas WooCommerce (separados por coma)

#### Ejemplo de uso

**Sincronización Síncrona:**
```bash
# Sincronización completa (actualizar existentes y crear nuevos)
POST /api/mercadolibre/products/12345/sync-woocommerce

# Solo actualizar productos existentes
POST /api/mercadolibre/products/12345/sync-woocommerce?mode=update

# Solo crear productos nuevos
POST /api/mercadolibre/products/12345/sync-woocommerce?mode=create

# Sincronizar solo con tiendas específicas
POST /api/mercadolibre/products/12345/sync-woocommerce?store_ids=1,2,3
```

**Sincronización Asíncrona (Recomendada):**
```bash
# Sincronización asíncrona completa
POST /api/mercadolibre/products/12345/sync-woocommerce-async

# Solo actualizar productos existentes (asíncrono)
POST /api/mercadolibre/products/12345/sync-woocommerce-async?mode=update

# Solo crear productos nuevos (asíncrono)
POST /api/mercadolibre/products/12345/sync-woocommerce-async?mode=create

# Sincronizar solo con tiendas específicas (asíncrono)
POST /api/mercadolibre/products/12345/sync-woocommerce-async?store_ids=1,2,3
```

#### Respuesta exitosa (Síncrono)

```json
{
  "status": "success",
  "message": "Sincronización con WooCommerce completada exitosamente",
  "data": {
    "client_id": "12345",
    "sync_mode": "sync",
    "total_ml_products": 150,
    "total_stores": 3,
    "stores_processed": 3,
    "products_updated": 45,
    "products_created": 12,
    "products_skipped": 8,
    "errors": [],
    "store_results": [
      {
        "store_id": 1,
        "store_name": "Tienda Principal",
        "products_updated": 20,
        "products_created": 5,
        "products_skipped": 2,
        "errors": []
      },
      {
        "store_id": 2,
        "store_name": "Tienda Secundaria",
        "products_updated": 15,
        "products_created": 4,
        "products_skipped": 3,
        "errors": []
      },
      {
        "store_id": 3,
        "store_name": "Tienda Tercera",
        "products_updated": 10,
        "products_created": 3,
        "products_skipped": 3,
        "errors": []
      }
    ]
  }
}
```

#### Respuesta exitosa (Asíncrono)

```json
{
  "status": "success",
  "message": "Sincronización asíncrona iniciada. Los productos se procesarán en segundo plano.",
  "data": {
    "client_id": "12345",
    "total_products": 150,
    "total_stores": 3,
    "total_jobs": 15,
    "sync_mode": "sync"
  }
}
```

#### Respuesta de error

```json
{
  "status": "error",
  "message": "No se encontraron productos en MercadoLibre para sincronizar."
}
```

## Funcionalidades

### 1. Extracción de SKU
- Extrae el campo "model" de los atributos de MercadoLibre
- Busca en múltiples atributos: MODEL, MODELO, SELLER_SKU, SKU, CODIGO, REFERENCE
- Si no encuentra SKU, omite el producto

### 2. Sincronización de Precios
- Sincroniza el campo `price` de MercadoLibre con `regular_price` de WooCommerce
- Convierte el precio a string como requiere WooCommerce

### 3. Sincronización de Stock
- Sincroniza `available_quantity` con `stock_quantity` de WooCommerce
- Actualiza `stock_status` basado en la cantidad disponible
- Habilita `manage_stock` para control de inventario

### 4. Creación de Productos
Para productos que no existen en WooCommerce:
- Crea productos simples (no variables)
- Usa el título de MercadoLibre como nombre del producto
- Establece estado como "publish"
- Incluye imagen principal si está disponible
- Usa descripción básica del título

### 5. Actualización de Productos
Para productos existentes en WooCommerce:
- Actualiza precio regular
- Actualiza cantidad de stock
- Actualiza estado de stock (instock/outofstock)
- Mantiene otros datos del producto intactos

## Logs

El sistema registra todas las operaciones en los logs de Laravel:
- Inicio y fin de sincronización
- Productos actualizados/creados por tienda
- Errores específicos por producto
- Errores de conexión con tiendas

## Consideraciones

1. **Autenticación**: Requiere token de autenticación válido
2. **Credenciales**: Necesita credenciales válidas de MercadoLibre
3. **Tiendas Activas**: Solo procesa tiendas WooCommerce marcadas como activas
4. **Rate Limiting**: Incluye pausas entre llamadas para evitar sobrecargar las APIs
5. **Manejo de Errores**: Continúa procesando aunque algunos productos fallen
6. **Logging**: Registra todas las operaciones para auditoría

## Ejemplo de Integración

```javascript
// Frontend - Llamada a la API
const syncProducts = async (clientId, mode = 'sync', storeIds = null) => {
  try {
    const params = new URLSearchParams();
    if (mode) params.append('mode', mode);
    if (storeIds) params.append('store_ids', storeIds.join(','));
    
    const response = await fetch(`/api/mercadolibre/products/${clientId}/sync-woocommerce?${params}`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
      }
    });
    
    const result = await response.json();
    
    if (result.status === 'success') {
      console.log('Sincronización exitosa:', result.data);
      // Mostrar resultados al usuario
    } else {
      console.error('Error en sincronización:', result.message);
    }
  } catch (error) {
    console.error('Error de conexión:', error);
  }
};
```
