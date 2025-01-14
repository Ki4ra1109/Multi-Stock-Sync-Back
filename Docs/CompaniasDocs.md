# Companias (Companies) endpoints documentation
These endpoints are used to list, create, modify, and delete companies from the data, which will be used to create warehouses and store products.

## Endpoints

### 1. **Create company**
**POST** `/api/companies`

This endpoint uses `name` as a mandatory string field to create a company. You simply choose a name and create it.

#### Body
```json
{
    "name": "pruebas",
}
```

#### Response (sucess)
```json
{
    "message": "Empresa creada con éxito.",
    "data": {
        "name": "pruebas",
        "updated_at": "2025-01-14T18:41:54.000000Z",
        "created_at": "2025-01-14T18:41:54.000000Z",
        "id": 3
    }
}
```

#### Response (Error: no name in body)
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

### 2. **List companies**
**GET** `/api/companies`

This endpoint list full companies list, with their assigned warehouses.

#### Body
```json
[
    {
        "id": 1,
        "name": "Marcos Reyes Testeo",
        "created_at": "2025-01-14T14:30:48.000000Z",
        "updated_at": "2025-01-14T18:17:17.000000Z",
        "warehouses": [
            {
                "id": 1,
                "name": "Yuyuko Warehouse",
                "location": "no especificado",
                "assigned_company_id": 1,
                "created_at": "2025-01-14T14:31:10.000000Z",
                "updated_at": "2025-01-14T14:31:10.000000Z"
            },
            {
                "id": 2,
                "name": "Yuyuko Warehouse 2",
                "location": "no especificado",
                "assigned_company_id": 1,
                "created_at": "2025-01-14T14:40:49.000000Z",
                "updated_at": "2025-01-14T14:40:49.000000Z"
            },
            // More warehouses here...
        ]
    },
    {
        "id": 3,
        "name": "pruebas",
        "created_at": "2025-01-14T18:41:54.000000Z",
        "updated_at": "2025-01-14T18:41:54.000000Z",
        "warehouses": []
    }
]
```

### 3. **List company by id**
**GET** `/api/companies/{id}`

This endpoint list a company info, using its id.

#### Body
```json
{
    "message": "Empresa encontrada con éxito.",
    "data": {
        "id": 1,
        "name": "Marcos Reyes Testeo",
        "created_at": "2025-01-14T14:30:48.000000Z",
        "updated_at": "2025-01-14T18:17:17.000000Z",
        "warehouses": [
            {
                "id": 1,
                "name": "Yuyuko Warehouse",
                "location": "no especificado",
                "assigned_company_id": 1,
                "created_at": "2025-01-14T14:31:10.000000Z",
                "updated_at": "2025-01-14T14:31:10.000000Z"
            },
            {
                "id": 2,
                "name": "Yuyuko Warehouse",
                "location": "no especificado",
                "assigned_company_id": 1,
                "created_at": "2025-01-14T14:40:49.000000Z",
                "updated_at": "2025-01-14T14:40:49.000000Z"
            },
            // More warehouses here...
        ]
    }
}
```

### 4. **Change company name**
**PATCH** `/api/companies/{id}`

This endpoint uses `name` for the company name using its id.

#### Body
```json
{
    "name": "nuevo_nombre",
}
```

#### Response (sucess)
```json
{
    "message": "Nombre de la empresa actualizado con éxito.",
    "data": {
        "id": 1,
        "name": "nuevo_nombre",
        "created_at": "2025-01-14T14:30:48.000000Z",
        "updated_at": "2025-01-14T18:46:58.000000Z"
    }
}
```

### 5. **Delete a company**
**DELETE** `/api/companies/{id}`

This endpoint deletes a company. 

>Warning: if a company has assigned warehouses, they will also be deleted.

#### Body
```json
{
    "message": "Empresa eliminada con éxito."
}
```

