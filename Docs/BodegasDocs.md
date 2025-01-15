# Bodegas (Warehouses) Documentation

These endpoints are used for the creation, modification, and deletion of bodegas (warehouses) in the Multi-Stock-Sync API.

## Endpoints

### 1. **Create a warehouse** 

**POST** `/api/warehouses`

This is the main endpoint to create a warehouse, for this we need the following mandatory attributes:

`name` (String): a name for our warehouse, e.g., Moisés' warehouse.

`assigned_company_id` (Int): the unique identifier of a company, to obtain it please refer to the companies documentation.

Optional field `location` : A string to specify where the warehouse is located.

#### Request Body
```json
{
    "name":"Yuyuko Warehouse",
    "assigned_company_id":2
}
```

#### Response (Success)
```json
{
    "message": "Bodega creada con éxito.",
    "data": {
        "location": "no especificado",
        "name": "Yuyuko Warehouse",
        "assigned_company_id": 1,
        "updated_at": "2025-01-14T14:31:10.000000Z",
        "created_at": "2025-01-14T14:31:10.000000Z",
        "id": 1
    }
}
```

#### Response (Error: Company ID not exists)
```json
{
    "message": "Datos de validación incorrectos.",
    "errors": {
        "assigned_company_id": [
            "La empresa asignada debe existir."
        ]
    }
}
```

#### Response (Error: one or more fields missing)
```json
{
    "message": "Datos de validación incorrectos.",
    "errors": {
        "name":[
            "El nombre es requerido."
        ]
        "assigned_company_id": [
            "La empresa asignada debe existir."
        ]
    }
}
```

### 2. **List warehouses **

**GET** `/api/warehouses`

With this method, all warehouses from all companies are listed to retrieve the total stored data.

#### Response

```json
[
    {
        "id": 1,
        "name": "Empresa pruebas",
        "created_at": "2025-01-14T14:30:48.000000Z",
        "updated_at": "2025-01-14T14:30:48.000000Z",
        "warehouses": [
            {
                "id": 1,
                "name": "Yuyuko Warehouse",
                "location": "no especificado",
                "assigned_company_id": 1,
                "created_at": "2025-01-14T14:31:10.000000Z",
                "updated_at": "2025-01-14T14:31:10.000000Z"
            },
            // more warehouses...
        ]
    },
    {
        "id": 2,
        "name": "Heladitos flash",
        "created_at": "2025-01-14T14:52:14.000000Z",
        "updated_at": "2025-01-14T14:52:14.000000Z",
        "warehouses": [
            {
                "id": 6,
                "name": "Yuyuko Warehouse",
                "location": "no especificado",
                "assigned_company_id": 2,
                "created_at": "2025-01-14T14:52:31.000000Z",
                "updated_at": "2025-01-14T14:52:31.000000Z"
            },
            // more warehouses...
        ]
    }
]
```

### 3. **List warehouses (by id)**

**GET** `/api/warehouses/{id}`
This method retrieves details of a specific warehouse using its id specified in the endpoint.

#### Response
```json
{
    "message": "Bodega encontrada con éxito.",
    "data": {
        "id": 2,
        "name": "Yuyuko Warehouse",
        "location": "no especificado",
        "assigned_company_id": 1,
        "created_at": "2025-01-14T14:40:49.000000Z",
        "updated_at": "2025-01-14T14:40:49.000000Z",
        "company": {
            "id": 1,
            "name": "Marcos Reyes Testeo",
            "created_at": "2025-01-14T14:30:48.000000Z",
            "updated_at": "2025-01-14T18:17:17.000000Z"
        }
    }
}
```

### 4. **Update warehouse name**

**PATCH** `/api/warehouses/{id}`
This method retrieves to change the name of a warehouse, e.g. Yuyuko Warehouse => Cirno Warehouse.

#### Request Body
```json
{
    "name":"Marcos Reyes Testeo"
}
```

#### Response
```json
{
    "message": "Nombre de la empresa actualizado con éxito.",
    "data": {
        "id": 2,
        "name": "Marcos Reyes Testeo",
        "created_at": "2025-01-14T14:52:14.000000Z",
        "updated_at": "2025-01-14T18:34:07.000000Z"
    }
}
```

#### Response (Error: one or more fields missing)
```json
{
    "message": "Datos de validación incorrectos.",
    "errors": {
        "name": [
            "The name field is required."
        ]
    }
}
```

### 5. **Delete a warehouse**

**DELETE** `/api/warehouses/{id}`
This method deletes a warehouse, what more can we say.

#### Response
```json
{
    "message": "Empresa eliminada con éxito."
}