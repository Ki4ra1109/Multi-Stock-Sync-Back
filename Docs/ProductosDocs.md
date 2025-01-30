## 3. Productos endpoints


- **Productos list**
    - **URL:** `/productos`
    - **Method:** `GET`
    - **Response Example:**
    ```json
    [
        {
        "id": 1,
        "nombre": "Peluche FumoFumos edicion limitada",
        "sku": "PEL-7894",
        "tipo": 1,
        "marca": {
            "id": 1,
            "nombre": "Sin Marca",
            "imagen": "",
            "created_at": "2025-01-08T13:39:15.000000Z",
            "updated_at": "2025-01-08T13:39:15.000000Z"
        },
        "control_stock": 1,
        "precio": "9990.00",
        "permitir_venta_no_stock": 0,
        "nombre_variante": null,
        "control_series": 1,
        "permitir_venta_decimales": 0,
        "created_at": "2025-01-08T16:40:38.000000Z",
        "updated_at": "2025-01-08T16:40:38.000000Z",
        "tipo_producto": {
            "id": 1,
            "producto": "No especificado",
            "created_at": "2025-01-08T13:39:16.000000Z",
            "updated_at": "2025-01-08T13:39:16.000000Z"
        },
        "stock": null
    },
    ]
    ```

- **Create producto**
    - **URL:** `/productos`
    - **Method:** `POST`
    - **Parameters:**
        - `nombre` (string, required)
        - `extranjero` (boolean, required)
        - `tipo` (int, required)
        - `marca` (int, required)
        - `sku` (string, optional) // If you don't send it, the system generates one.
        - `control_stock` (boolean, required)
        - `precio` (int, required)
        - `permitir_venta_no_stock` (boolean, required)
        - `control_series` (boolean, required)
        - `permitir_venta_decimales` (boolean, required)
    - **Request Example:**
    ```json
    {
        "nombre": "Ventilador Amarillo",
        "tipo": 1, // "no especificado"
        "marca": 1, // "sin marca"
        "sku":"MLC_2813812", // "optional field"
        "control_stock": true,
        "precio":25990,
        "permitir_venta_no_stock": false,
        "control_series": false,
        "permitir_venta_decimales": false
    }

    ```
    - **Response Example:**
    ```json
    {
        "message": "Producto creado correctamente",
        "producto": {
            "nombre": "Ventilador Amarillo",
            "sku": "MLC_2813812",
            "tipo": 1,
            "marca": 1,
            "control_stock": true,
            "precio": 25990,
            "permitir_venta_no_stock": false,
            "control_series": false,
            "permitir_venta_decimales": false,
            "updated_at": "2025-01-09T16:55:03.000000Z",
            "created_at": "2025-01-09T16:55:03.000000Z",
            "id": 22
        }
    }
    ```

- **Update producto**
    - **URL:** `/productos/{id}`
    - **Method:** `PATCH`
    - **Parameters:**
        - `nombre` (string, optional)
        - `extranjero` (boolean, optional)
        - `tipo` (int, optional)
        - `marca` (int, optional)
        - `sku` (string, optional) // If you don't send it, the system generates one.
        - `control_stock` (boolean, optional)
        - `precio` (int, optional)
        - `permitir_venta_no_stock` (boolean, optional)
        - `control_series` (boolean, optional)
        - `permitir_venta_decimales` (boolean, optional)
    - **Request Example:**
    ```json
    {
        "nombre": "Ventilador Amarillo",
        "tipo": 1, // "no especificado"
        "marca": 1, // "sin marca"
        "control_stock": true,
        "precio":25990,
        "permitir_venta_no_stock": false,
        "control_series": false,
        "permitir_venta_decimales": false
    }

    ```
    - **Response Example:**
    ```json
    {
        "message": "Producto actualizado correctamente",
        "producto": {
            "id": 22,
            "nombre": "Ventilador Amarillo",
            "sku": "VEN-9931",
            "tipo": 1,
            "marca": 1,
            "control_stock": true,
            "precio": "25990.00",
            "permitir_venta_no_stock": false,
            "nombre_variante": null,
            "control_series": false,
            "permitir_venta_decimales": false,
            "created_at": "2025-01-09T16:55:03.000000Z",
            "updated_at": "2025-01-09T18:03:37.000000Z"
        }
    }
    ```
- **Delete producto**
    - **URL:** `/productos/{id}`
    - **Method:** `DELETE`
    - **Response Example:**
    ```json
    {
    "message": "Producto eliminado correctamente"
    }
    ```
