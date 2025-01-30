## 4. Marcas endpoints

- **Marcas list**
    - **URL:** `/marcas`
    - **Method:** `GET`
    - **Response Example:**
    ```json
    [
        {
        "id": 1,
        "nombre": "Sin Marca",
        "imagen": "",
        "created_at": "2025-01-08T13:39:15.000000Z",
        "updated_at": "2025-01-08T13:39:15.000000Z"
        },
        {
            "id": 2,
            "nombre": "Marca Fumo",
            "imagen": "https://example.com/logo-fumo.png",
            "created_at": "2025-01-08T16:42:00.000000Z",
            "updated_at": "2025-01-08T16:42:00.000000Z"
        },
    ]
    ```

- **Create marca**
    - **URL:** `/marcas`
    - **Method:** `POST`
    - **Parameters:**
        - `nombre` (string, required)
        - `imagen` (string, optional)
    - **Request Example:**
    ```json
    {
        "nombre": "Marca Fumo",
        "imagen": "https://example.com/logo-fumo.png",
    }
    ```
    - **Response Example:**
    ```json
    {
        "message": "Marca creada correctamente",
        "marca": {
            "nombre": "Marca Fumo",
            "imagen": "https://example.com/logo-fumo.png",
            "updated_at": "2025-01-09T18:11:35.000000Z",
            "created_at": "2025-01-09T18:11:35.000000Z",
            "id": 20
        }
    }
    ```

- **Update marca**
    - **URL:** `/marcas/{id}`
    - **Method:** `PATCH`
    - **Parameters:**
        - `nombre` (string, optional)
        - `imagen` (string, optional)
    - **Request Example:**
    ```json
    {
        "nombre": "Marca Fumo 2",
        "imagen": "https://example.com/logo-fumo.png",
    }
    ```
    - **Response Example:**
    ```json
    {
        "message": "Marca actualizada parcialmente correctamente",
        "marca": {
            "id": 2,
            "nombre": "Marca Fumo 2",
            "imagen": "https://example.com/logo-fumo.png",
            "created_at": "2025-01-08T16:42:00.000000Z",
            "updated_at": "2025-01-09T18:16:39.000000Z"
        }
    }
    ```

- **Delete marca**
    - **URL:** `/marcas/{id}`
    - **Method:** `DELETE`
    - **Response Example:**
    ```json
    {
        "message": "Marca eliminada correctamente"
    }
    ```
