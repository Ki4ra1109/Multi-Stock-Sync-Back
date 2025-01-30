## 2. Clientes endpoints

Endpoints dedicated to the creation of new "clients" for the user's business, interested in purchasing at their point of sale.

- **Clientes list**
    - **URL:** `/clientes`
    - **Method:** `GET`
    - **Response Example:**
    ```json
    "data": [
        {
            "id": 1,
            "tipo_cliente_id": 1,
            "extranjero": 0,
            "rut": "12345678-9",
            "razon_social": "Empresa Ejemplo",
            "giro": "Comercio",
            "nombres": "Juan",
            "apellidos": "Pérez",
            "direccion": "Calle Falsa 123",
            "comuna": "Santiago",
            "region": "Metropolitana",
            "ciudad": "Santiago",
            "created_at": "2025-01-07T14:50:30.000000Z",
            "updated_at": "2025-01-07T14:50:30.000000Z",
            "tipo_cliente": {
                "id": 1,
                "tipo": "Empresa",
                "created_at": "2025-01-07T14:49:41.000000Z",
                "updated_at": "2025-01-07T14:49:41.000000Z"
            }
        },
        {
            "id": 2,
            "tipo_cliente_id": 2,
            "extranjero": 0,
            "rut": "12345678-9",
            "razon_social": "Persona",
            "giro": "Comercio",
            "nombres": "Juan 2",
            "apellidos": "2",
            "direccion": "Calle Falsa 444",
            "comuna": "Santiago",
            "region": "Metropolitana",
            "ciudad": "Santiago",
            "created_at": "2025-01-07T14:50:52.000000Z",
            "updated_at": "2025-01-07T14:50:52.000000Z",
            "tipo_cliente": {
                "id": 2,
                "tipo": "Persona",
                "created_at": "2025-01-07T14:49:41.000000Z",
                "updated_at": "2025-01-07T14:49:41.000000Z"
            }
        }
    ]
    ```
- **Create cliente**
    - **URL:** `/clientes`
    - **Method:** `POST`
    - **Parameters:**
        - `tipo_cliente_id` (int, required) use 1:empresa or 2:persona only.
        - `extranjero` (boolean, required)
        - `rut` (string, required)
        - `razon_social` (string, required)
        - `giro` (string, required)
        - `nombres` (string, required)
        - `apellidos` (string, required)
        - `direccion` (string, required)
        - `comuna` (string, required)
        - `region` (string, required)
        - `ciudad` (string, required)
    - **Request Example:**
    ```json
    {
        "tipo_cliente_id": 2,
        "extranjero": false,
        "rut": "12345678-9",
        "razon_social": "Persona",
        "giro": "Comercio",
        "nombres": "Juan 2",
        "apellidos": "2",
        "direccion": "Calle Falsa 444",
        "comuna": "Santiago",
        "region": "Metropolitana",
        "ciudad": "Santiago"
    }
    ```
    - **Response Example:**
    ```json
    {
        "message": "Cliente creado con éxito",
        "data": {
            "tipo_cliente_id": 2,
            "extranjero": false,
            "rut": "12345678-9",
            "razon_social": "Persona",
            "giro": "Comercio",
            "nombres": "Juan 2",
            "apellidos": "2",
            "direccion": "Calle Falsa 444",
            "comuna": "Santiago",
            "region": "Metropolitana",
            "ciudad": "Santiago",
            "updated_at": "2025-01-07T15:39:14.000000Z",
            "created_at": "2025-01-07T15:39:14.000000Z",
            "id": 3
        }
    }
    ```
- **Update cliente**
    - **URL:** `/clientes/{id}`
    - **Method:** `PATCH`
    - **Parameters:**
        - `tipo_cliente_id` (int, optional)
        - `extranjero` (boolean, optional)
        - `rut` (string, optional)
        - `razon_social` (string, optional)
        - `giro` (string, optional)
        - `nombres` (string, optional)
        - `apellidos` (string, optional)
        - `direccion` (string, optional)
        - `comuna` (string, optional)
        - `region` (string, optional)
        - `ciudad` (string, optional)
    - **Request Example:**
    ```json
    {
        "tipo_cliente_id": 2,
        "extranjero": false,
        "rut": "12345678-9",
        "razon_social": "Persona",
        "giro": "Comercio",
        "nombres": "Marcos",
        "apellidos": "Reyes",
        "direccion": "Calle Falsa 444",
        "comuna": "Santiago",
        "region": "Metropolitana",
        "ciudad": "Santiago"
    }
    ```
    - **Response Example:**
    ```json
    {
        "message": "Cliente actualizado con éxito",
        "data": {
            "id": 2,
            "tipo_cliente_id": 2,
            "extranjero": false,
            "rut": "12345678-9",
            "razon_social": "Persona",
            "giro": "Comercio",
            "nombres": "Marcos",
            "apellidos": "Reyes",
            "direccion": "Calle Falsa 444",
            "comuna": "Santiago",
            "region": "Metropolitana",
            "ciudad": "Santiago",
            "created_at": "2025-01-09T15:30:21.000000Z",
            "updated_at": "2025-01-09T15:48:21.000000Z"
        }
    }
    ```

- **Delete cliente**
    - **URL:** `/clientes/{id}`
    - **Method:** `DELETE`
    - **Response Example:**
    ```json
    {
        "message": "Cliente eliminado con éxito"
    }
    ```
