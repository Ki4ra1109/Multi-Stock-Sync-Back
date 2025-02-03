# MercadoLibre API Integration Documentation

## Table of Contents
1. [Introduction](#introduction)
2. [Connections](#connections)
   - [Test and Refresh Connection](#test-and-refresh-connection)
3. [Credentials](#credentials)
   - [Get All Credentials Data](#get-all-credentials-data)
   - [Get Credentials by Client ID](#get-credentials-by-client-id)
   - [Delete Credentials](#delete-credentials)
4. [Login](#login)
   - [Handle Callback](#handle-callback)
   - [Login](#login-endpoint)
5. [Products](#products)
   - [Get Product Reviews](#get-product-reviews)
   - [List Products by Client ID](#list-products-by-client-id)
   - [Save Products](#save-products)
   - [Search Products](#search-products)
6. [Reports](#reports)
   - [Compare Annual Sales Data](#compare-annual-sales-data)
   - [Compare Sales Data](#compare-sales-data)
   - [Get Annual Sales](#get-annual-sales)
   - [Get Daily Sales](#get-daily-sales)
   - [Get Invoice Report](#get-invoice-report)
   - [Get Order Statuses](#get-order-statuses)
   - [Get Refunds By Category](#get-refunds-by-category)
   - [Get Sales By Date Range](#get-sales-by-date-range)
   - [Get Sales By Month](#get-sales-by-month)
   - [Get Sales By Week](#get-sales-by-week)
   - [Get Top Payment Methods](#get-top-payment-methods)
   - [Get Top Selling Products](#get-top-selling-productos)
   - [Get Weeks Of Month](#get-weeks-of-month)
   - [Summary](#summary)

---

## Introduction
This document provides a detailed reference for the MercadoLibre API, listing all available endpoints, their respective HTTP methods, expected request parameters, and response formats. This guide is intended to assist front-end developers in integrating MercadoLibre services efficiently.

## Connections
---
### Test and Refresh Connection
**Endpoint:** `/api/MercadoLibre/connections/testaAndRefreshConnectionController`
**Method:** `GET`
**Description:** Tests and refreshes the connection with MercadoLibre.
#### Response (SUCCESS)

```json
{
    
"status": "success",
    "message": "Connection successful.",
    "data": {
        "id": 123456789,
        "nickname": "TestUser",
        "email": "testuser@example.com"
    }
    
}
```

#### Response (ERROR)

```json
{
    "status": "error",
                "message": "Error al conectar con MercadoLibre. El token podría ser inválido."
}
```

## Credentials
---
### Get All Credentials Data
**Endpoint:** `/api/mercadolibre/credentials/getAllCredentialsController`
**Method:** `GET`
**Description:** Retrieves all stored MercadoLibre credentials.
#### Response (SUCCESS)

```json
{
  "status": "success",
  "data": {
    "client_id": "12345",
    "client_secret": "your-client-secret",
    "access_token": "your-access-token",
    "refresh_token": "your-refresh-token",
    "expires_at": "2025-01-31T12:00:00",
    // Otros campos de credenciales según nuestro modelo
  }
}

```

#### Response (ERROR)

```json
{
  "status": "error",
  "message": "No se encontraron credenciales."
}

```

### Get Credentials by Client ID
**Endpoint:** `/api/mercadolibre/credentials/getCredentialsByClientIdController`
**Method:** `GET`
**Description:** Fetches MercadoLibre credentials for a specific client ID.
#### Response (SUCCESS)

```json
{
    "Status": "success",
    "data": "$credentials"
}
```

#### Response (ERROR)

```json
{
    "status": "error",
    "message": "Credenciales no encontradas."
}
```

### Delete Credentials
**Endpoint:** `/api/mercadolibre/credentials/deleteCredentialsController`
**Method:** `DELETE`
**Description:** Deletes stored credentials by ID.
**Parameters:**
- `credentialId` (string) - The ID of the credentials to delete.
#### Response (SUCCESS)

```json
{
    "Status": "success",
    "data": "$credentials"
}
```

#### Response (ERROR)

```json
{
    "status": "error",
    "message": "Credenciales no encontradas."
}
```

## Login
---
### Handle Callback
**Endpoint:** `/api/mercadolibre/login/handlecCallbackController`
**Method:** `GET`
**Description:** Handles the MercadoLibre OAuth callback.
#### Response (SUCCESS)

```json
{
    "Status": "success",
    "message": "Autorización completada correctamente."
}

```

#### Response (ERROR)

1. **Missing Parameters (400 Bad Request)**  
   - Happens when the `code` or `state` parameters are not present in the callback request.
```json
 {
       "status": "error",
       "message": "No se encontró un token para estas credenciales. Por favor, inicie sesión primero."
   }
```
2. **Invalid or Expired State (400 Bad Request)**  
   - Occurs when the state parameter is missing from the cache or has expired.

```json
{
    "status": "error",
    "message": "El token ha expirado. Por favor, renueve la conexión."
}
```
3. **Credentials Not Found (500 Internal Server Error)**  
   - Triggered when the credentials associated with the state cannot be found in the database.

```json
{
    "status": "error",
    "message": "Error al conectar con MercadoLibre. El token podría ser inválido."
}
```


### Login
**Endpoint:** `/api/mercadolibre/login`
**Method:** `POST`
**Description:** Initiates the login process with MercadoLibre.
#### Response (SUCCESS)

```json
{
    "status": "success",
    "message": "Credenciales validadas. Redirigiendo a Mercado Libre...",
    "redirect_url": "https://auth.mercadolibre.cl/authorization?response_type=code&client_id=CLIENT_ID&redirect_uri=REDIRECT_URI&state=STATE"
}
```

#### Response (ERROR)
```json
{
    "status": "error",
    "message": "Credenciales inválidas. Por favor, verifique e intente nuevamente."
}
```

## Products
---
### Get Product Reviews
**Endpoint:** `/api/mercadolibre/products/getProductReviewsController`
**Method:** `GET`
**Description:** Fetches reviews for a product.
#### Response (SUCCESS)

```json
{
    "status": "success",
    "message": "Opiniones obtenidas con éxito.",
    "data": { /* Datos de las opiniones sobre el producto */ }
}

```

#### Response (ERROR)

1. **Credentials Not Found (404 Not Found)**  
   - Occurs when valid credentials for the provided client_id are not found. This could happen if the client_id does not exist in the database or the provided credentials are incorrect.

```json
{
    "status": "error",
    "message": "No se encontraron credenciales válidas para el client_id proporcionado."
}
```
2. **Expired Token (401 Unauthorized)**  
   - Occurs when the stored token has expired and needs to be refreshed. This error indicates that the user must refresh the access token to continue making requests.

```json
{
    "status": "error",
    "message": "El token ha expirado. Por favor, renueve su token."
}

```
3. **API Connection Error (500 Internal Server Error)**  
   - Occurs when there is a problem connecting to the MercadoLibre API, typically due to network issues or a malfunction in the MercadoLibre service.

```json
{
    "status": "error",
    "message": "Error al conectar con la API de MercadoLibre."
}
```

### List Products by Client ID
**Endpoint:** `/api/mercadolibre/products/listProductByClientIdController`
**Method:** `GET`
**Description:** Lists products associated with a given client ID.
#### Response (SUCCESS)

```json
{
    "status": "success",
    "message": "Productos obtenidos con éxito.",
    "data": [
        {
            "id": "MLA1234567890",
            "title": "Producto Ejemplo",
            "price": 1999.99,
            "currency_id": "ARS",
            "available_quantity": 10,
            "sold_quantity": 5,
            "thumbnail": "https://example.com/thumbnail.jpg",
            "permalink": "https://mercadolibre.com/producto-ejemplo",
            "status": "active",
            "category_id": "MLA1234"
        }
    ],
    "pagination": {
        "total": 1,
        "limit": 50,
        "offset": 0
    }
}
```

#### Response (ERROR)

1. **Credentials Not Found (404 Not Found)**  
   - Occurs when valid credentials for the provided client_id are not found in the database. The client_id provided doesn't exist or is incorrect.

```json
{
    "status": "error",
    "message": "No se encontraron credenciales válidas para el client_id proporcionado."
}
```
2. **Expired Token (401 Unauthorized)**  
   - Occurs when the stored token has expired and needs to be refreshed. The user must update their token to continue making requests.

```json
{
    "status": "error",
    "message": "El token ha expirado. Por favor, renueve su token."
}

```
3. **User ID Fetch Error (500 Internal Server Error)**  
   - Occurs when the system fails to retrieve the user ID from MercadoLibre using the provided token. This may happen due to invalid or expired tokens or issues on the MercadoLibre API.

```json
{
    "status": "error",
    "message": "No se pudo obtener el ID del usuario. Por favor, valide su token.",
    "error": { /* Error details from MercadoLibre API */ }
}

```
4. **API Connection Error (500 Internal Server Error)**  
   - Occurs when there is an issue connecting to the MercadoLibre API, such as network issues or problems on MercadoLibre's servers.

```json
{
    "status": "error",
    "message": "Error al conectar con la API de MercadoLibre.",
    "error": { /* Error details from MercadoLibre API */ }
}

```

### Save Products
**Endpoint:** `/api/mercadolibre/products/saveProductsController`
**Method:** `POST`
**Description:** Saves products retrieved from the MercadoLibre API to the database.
#### Response (SUCCESS)

```json
{
    "status": "success",
    "message": "Productos guardados con éxito",
    "data": {
        "saved_products": 50
    }
}
```
#### Response (ERROR)

1. **Save Error (500 Internal Server Error)**  
   - Occurs when an error happens while saving the products into the database. This could be due to database connectivity issues, API issues, or an error during data processing.

```json
{
    "status": "error",
    "message": "Error al guardar los productos",
    "error": "Descripción del error que ocurrió durante el intento de guardar los productos"
}

```

### Search Products
**Endpoint:** `/api/mercadolibre/products/searchProductsController `
**Method:** `GET`
**Description:** Searches for products on MercadoLibre using the provided client_id and search term.
#### Response (SUCCESS)

```json
{
    "status": "success",
    "message": "Productos obtenidos con éxito.",
    "client": {
        "id": "123456",
        "nickname": "usuario123",
        "email": "usuario123@example.com",
        "country_id": "AR"
    },
    "data": [
        {
            "id": "MLA12345",
            "title": "Producto de ejemplo",
            "price": 1000,
            "currency_id": "ARS",
            "available_quantity": 10,
            "sold_quantity": 5,
            "thumbnail": "https://example.com/thumbnail.jpg",
            "permalink": "https://mercadolibre.com/producto12345",
            "status": "active",
            "category_id": "MLA123"
        }
    ],
    "pagination": {
        "total": 100,
        "limit": 50,
        "offset": 0
    }
}

```

#### Response (ERROR)

1. **Invalid Credentials (404 Not Found)**  
   - Occurs when no valid credentials are found for the provided client_id.
```json
{
    "status": "error",
    "message": "No se encontraron credenciales válidas para el client_id proporcionado."
}
```
2. **Expired Token (401 Unauthorized)**  
   - Occurs when the stored token has expired and needs to be refreshed.
```json
{
    "status": "error",
    "message": "El token ha expirado. Por favor, renueve su token."
}
```
3. **User Info Fetch Error (500 Internal Server Error)**  
   - Occurs when there is an issue fetching the user information from the MercadoLibre API, potentially due to invalid token or network issues.
```json
{
    "status": "error",
    "message": "No se pudo obtener el ID del usuario. Por favor, valide su token.",
    "error": {
        "message": "Descripción del error ocurrido durante la obtención del ID del usuario"
    }
}
```
4. **API Connection Error (500 Internal Server Error)**  
   - Occurs when there is an issue connecting to the MercadoLibre API to fetch product data.
```json
{
    "status": "error",
    "message": "Error al conectar con la API de MercadoLibre.",
    "error": {
        "message": "Descripción del error ocurrido durante la conexión con la API de MercadoLibre"
    }
}

```
5. **Product Fetch Error (500 Internal Server Error)**  
   - Occurs when there is an issue fetching detailed information for a product.
```json
{
    "status": "error",
    "message": "Error al obtener la información detallada de los productos.",
    "error": {
        "message": "Descripción del error ocurrido durante la obtención de la información del producto"
    }
}
```

## Reports
---
### Compare Annual Sales Data
**Endpoint:** `/api/mercadolibre/reportes/compareAnnualSalesDataController`
**Method:** `GET`
**Description:** Compares annual sales data.
#### Response (SUCCESS)

```json
{
    "status": "success",
    "message": "Comparación de ventas obtenida con éxito.",
    "data": {
        "year1": {
            "year": "2023",
            "total_sales": 15000,
            "sold_products": [
                {
                    "order_id": "12345",
                    "order_date": "2023-01-15T10:00:00",
                    "title": "Producto A",
                    "quantity": 2,
                    "price": 500
                }
            ]
        },
        "year2": {
            "year": "2024",
            "total_sales": 18000,
            "sold_products": [
                {
                    "order_id": "67890",
                    "order_date": "2024-02-05T11:30:00",
                    "title": "Producto B",
                    "quantity": 3,
                    "price": 600
                }
            ]
        },
        "difference": 3000,
        "percentage_change": 20
    }
}

```

#### Response (ERROR)

1. **Invalid Credentials (404 Not Found)**  
   - Occurs when no valid credentials are found for the provided client_id.

```json
{
    "status": "error",
    "message": "No se encontraron credenciales válidas para el client_id proporcionado."
}
```
2. **Expired Token (401 Unauthorized)**  
   - Occurs when the stored token has expired and needs to be refreshed.

```json
{
    "status": "error",
    "message": "El token ha expirado. Por favor, renueve su token."
}
```
3. **User Info Fetch Error (500 Internal Server Error)**  
   - Occurs when there is an issue fetching the user information from the MercadoLibre API, potentially due to invalid token or network issues.

```json
{
    "status": "error",
    "message": "No se pudo obtener el ID del usuario. Por favor, valide su token.",
    "error": {
        "message": "Descripción del error ocurrido durante la obtención del ID del usuario"
    }
}
```
4. **API Connection Error (500 Internal Server Error)**  
   - Occurs when there is an issue connecting to the MercadoLibre API to fetch the sales data.

```json
{
    "status": "error",
    "message": "Error al conectar con la API de MercadoLibre.",
    "error": {
        "message": "Descripción del error ocurrido durante la conexión con la API de MercadoLibre"
    }
}

```
5. **Missing Query Parameters (400 Bad Request)**  
   - Occurs when either year1 or year2 is not provided in the query.

```json
{
    "status": "error",
    "message": "Los parámetros de consulta year1 y year2 son obligatorios."
}
```

### Compare Sales Data
**Endpoint:** `/api/mercadolibre/reportes/compareSalesDataController`
**Method:** `GET`
**Description:** Compares sales data from two different months and provides an analysis of the sales, including the percentage change between the two months.
#### Response (SUCCESS)

```json
{
    "status": "success",
    "message": "Comparación de ventas obtenida con éxito.",
    "data": {
        "month1": {
            "year": "2024",
            "month": "01",
            "total_sales": 15000,
            "sold_products": [
                {
                    "order_id": "12345",
                    "order_date": "2024-01-15T12:34:56",
                    "title": "Producto A",
                    "quantity": 10,
                    "price": 100
                },
                {
                    "order_id": "12346",
                    "order_date": "2024-01-16T14:20:10",
                    "title": "Producto B",
                    "quantity": 5,
                    "price": 300
                }
            ]
        },
        "month2": {
            "year": "2024",
            "month": "02",
            "total_sales": 18000,
            "sold_products": [
                {
                    "order_id": "12347",
                    "order_date": "2024-02-10T10:20:30",
                    "title": "Producto A",
                    "quantity": 15,
                    "price": 100
                },
                {
                    "order_id": "12348",
                    "order_date": "2024-02-12T16:45:05",
                    "title": "Producto C",
                    "quantity": 8,
                    "price": 250
                }
            ]
        },
        "difference": 3000,
        "percentage_change": 20.0
    }
}
```

#### Response (ERROR)

1. **Token Expired (401 Unauthorized)**  
   - Occurs when the provided access token is expired and requires renewal.

```json
{
    "status": "error",
    "message": "El token ha expirado. Por favor, renueve su token.",
    "error": "Token expired"
}
```
2. **Missing Query Parameters (400 Bad Request)**  
   - Occurs when the required query parameters (month1, year1, month2, year2) are missing.

```json
{
    "status": "error",
    "message": "Los parámetros de consulta month1, year1, month2 y year2 son obligatorios.",
    "error": "Missing query parameters"
}
```
3. **Invalid Token (500 Internal Server Error)**  
   - Occurs when there is an issue with token validation or an API request failure to retrieve user information.

```json
{
    "status": "error",
    "message": "No se pudo obtener el ID del usuario. Por favor, valide su token.",
    "error": "Invalid token or API request failure"
}
```
4. **API Connection Failure (500 Internal Server Error)**  
   - Occurs when there is a failure to connect to the MercadoLibre API while fetching sales data.

```json
{
    "status": "error",
    "message": "Error al conectar con la API de MercadoLibre.",
    "error": "API connection failure"
}
```

### Get Annual Sales
**Endpoint:** `/api/mercadolibre/reportes/getAnnualSalesController`
**Method:** `GET`
**Description:** Retrieves annual sales reports.
#### Response (SUCCESS)

```json
{
    "status": "success",
    "message": "Ventas anuales obtenidas con éxito.",
    "data": {
        "2024-01": {
            "total_amount": 1500,
            "orders": [
                {
                    "id": "123456",
                    "date_created": "2024-01-15T12:34:56",
                    "total_amount": 100,
                    "status": "paid",
                    "sold_products": [
                        {
                            "order_id": "123456",
                            "order_date": "2024-01-15T12:34:56",
                            "title": "Producto A",
                            "quantity": 2,
                            "price": 50
                        }
                    ]
                }
            ]
        }
    }
}

```

#### Response (ERROR)

1. **No valid credentials found (404 Not Found)**  
   - Occurs when no valid credentials are found for the provided client_id. This means the client ID provided doesn't match any credentials in the system.

```json
{
    "status": "error",
    "message": "No se encontraron credenciales válidas para el client_id proporcionado."
}
```
2. **Token Expired (401 Unauthorized)**  
   - This error occurs when the provided token has expired. The user must renew their token to continue making API requests.

```json
{
    "status": "error",
    "message": "El token ha expirado. Por favor, renueve su token."
}

```
3. **Failed to Retrieve User ID (500 Internal Server Error)**  
   - Occurs when there is a problem retrieving the user ID from the MercadoLibre API. This could be due to an invalid or expired token, or issues on the API server side.

```json
{
    "status": "error",
    "message": "No se pudo obtener el ID del usuario. Por favor, valide su token.",
    "error": {
        "response": "Detalles del error retornados por la API"
    }
}

```

## Get Daily Sales
**Endpoint:** `/api/mercadolibre/reportes/getDailySalesController`
**Method:** `GET`
**Description:** Retrieves daily sales from the MercadoLibre API using the provided client_id. This allows obtaining a summary of the sales made on a specific date, including the total sales and the sold products.
#### Response (SUCCESS)

```json
{
    "status": "success",
    "message": "Ventas diarias obtenidas con éxito.",
    "data": {
        "date": "2025-02-02",
        "total_sales": 1500.00,
        "sold_products": [
            {
                "order_id": "123456789",
                "order_date": "2025-02-02T10:20:30.000-00:00",
                "title": "Producto A",
                "quantity": 3,
                "price": 50.00
            },
            {
                "order_id": "987654321",
                "order_date": "2025-02-02T12:45:20.000-00:00",
                "title": "Producto B",
                "quantity": 2,
                "price": 75.00
            }
        ]
    }
}

```

#### Response (ERROR)

1. **No valid credentials found (404 Not Found)**  
   - Occurs when no valid credentials are found for the provided client_id. This means the client ID doesn't match any existing credentials in the system.
```json
{
    "status": "error",
    "message": "No se encontraron credenciales válidas para el client_id proporcionado."
}
```
2. **Token Expired (401 Unauthorized)**  
   - Occurs when the token used for authentication has expired. The user needs to renew their token.
```json
{
    "status": "error",
    "message": "El token ha expirado. Por favor, renueve su token."
}
```
3. **Failed to obtain user ID (500 Internal Server Error)**  
   - Occurs when the API call to retrieve the user ID fails, typically due to invalid or expired token.
```json
{
    "status": "error",
    "message": "No se pudo obtener el ID del usuario. Por favor, valide su token.",
    "error": {
        "error_code": "some_error_code",
        "error_description": "description_of_the_error"
    }
}
```
4. **Failed to connect to MercadoLibre API (500 Internal Server Error)**  
   - Occurs when there is an issue with the connection to the MercadoLibre API. This could be due to a network failure or an issue with the API server.
```json
{
    "status": "error",
    "message": "Error al conectar con la API de MercadoLibre.",
    "error": {
        "error_code": "some_error_code",
        "error_description": "description_of_the_error"
    }
}
```

### Get Invoice Report
**Endpoint:** `/api/mercadolibre/reportes/getInvoiceReportController`
**Method:** `GET`
**Description:** Retrieves the invoice report from the MercadoLibre API based on the provided client_id, with options for grouping, document type, and pagination. The response includes invoice data for the specified parameters, such as group, document_type, and offset.
#### Response (SUCCESS)

```json
{
    "status": "success",
    "message": "Reporte de facturas obtenido con éxito.",
    "data": {
        "pagination": {
            "offset": 1,
            "limit": 2,
            "total": 100,
            "count": 2
        },
        "results": [
            {
                "id": "invoice_id_1",
                "group": "MP",
                "document_type": "BILL",
                "total_amount": 1000.00,
                "status": "PAID",
                "date": "2025-02-02",
                "due_date": "2025-02-15"
            },
            {
                "id": "invoice_id_2",
                "group": "MP",
                "document_type": "BILL",
                "total_amount": 1500.00,
                "status": "PAID",
                "date": "2025-02-01",
                "due_date": "2025-02-14"
            }
        ]
    }
}

```

#### Response (ERROR)

1. **No valid credentials found (404 Not Found)**  
   - Occurs when no valid credentials are found for the provided client_id. This means the client ID doesn't match any existing credentials in the system.

```json
{
    "status": "error",
    "message": "No se encontraron credenciales válidas para el client_id proporcionado."
}
```
2. **Token Expired (401 Unauthorized)**  
   - Occurs when the token used for authentication has expired. The user needs to renew their token.

```json
{
    "status": "error",
    "message": "El token ha expirado. Por favor, renueve su token."
}
```
3. **Failed to connect to MercadoLibre API (500 Internal Server Error)**  
   - Occurs when there is an issue with the connection to the MercadoLibre API. This could be due to a network failure or an issue with the API server.

```json
{
    "status": "error",
    "message": "Error al conectar con la API de MercadoLibre.",
    "error": {
        "error_code": "some_error_code",
        "error_description": "description_of_the_error"
    }
}
```

### Get Order Statuses
**Endpoint:** `/api/mercadolibre/reportes/getOrderStatusesController`
**Method:** `GET`
**Description:** Retrieves the order statuses (paid, pending, canceled) from the MercadoLibre API for the specified client_id. The response includes a count of orders in each status category.

#### Response (SUCCESS)

```json
{
    "status": "success",
    "message": "Estados de órdenes obtenidos con éxito.",
    "data": {
        "paid": 120,
        "pending": 50,
        "canceled": 10
    }
}
```

#### Response (ERROR)

1. **No valid credentials found (404 Not Found)**  
   - Occurs when no valid credentials are found for the provided client_id. This means the client ID doesn't match any existing credentials in the system.
```json
{
    "status": "error",
    "message": "No se encontraron credenciales válidas para el client_id proporcionado."
}
```
2. **Token Expired (401 Unauthorized)**  
   - Occurs when the token used for authentication has expired. The user needs to renew their token.
```json
{
    "status": "error",
    "message": "El token ha expirado. Por favor, renueve su token."
}
```
3. **Failed to get user ID (500 Internal Server Error)**  
   - Occurs when there is an issue with obtaining the user ID from the MercadoLibre API. This could be due to an invalid token or a failure in the API request.
```json
{
    "status": "error",
    "message": "No se pudo obtener el ID del usuario. Por favor, valide su token.",
    "error": {
        "error_code": "some_error_code",
        "error_description": "description_of_the_error"
    }
}

```
4. **Failed to connect to MercadoLibre API (500 Internal Server Error)**  
   - Occurs when there is an issue with the connection to the MercadoLibre API. This could be due to a network failure or an issue with the API server.
```json
{
    "status": "error",
    "message": "Error al conectar con la API de MercadoLibre.",
    "error": {
        "error_code": "some_error_code",
        "error_description": "description_of_the_error"
    }
}
```

### Get Refunds By Category
**Endpoint:** `/api/mercadolibre/reportes/getRefundsByCategoryController`
**Method:** `GET`
**Description:** Retrieves refunds or returns for orders based on category, date range, and other details from the MercadoLibre API for the specified client_id. The response includes details of refunds grouped by
#### Response (SUCCESS)

```json
{
    "status": "success",
    "message": "Refunds by category retrieved successfully.",
    "data": {
        "category_id_1": {
            "category_id": "category_id_1",
            "total_refunds": 5000.00,
            "orders": [
                {
                    "id": "order_id_1",
                    "created_date": "2025-02-01T12:30:00",
                    "total_amount": 1000.00,
                    "status": "cancelled",
                    "product": {
                        "title": "Product Title",
                        "quantity": 2,
                        "price": 500.00
                    },
                    "buyer": {
                        "id": "buyer_id_1",
                        "name": "Buyer Name"
                    },
                    "billing": {
                        "first_name": "John",
                        "last_name": "Doe",
                        "identification": {
                            "type": "ID",
                            "number": "123456789"
                        }
                    },
                    "shipping": {
                        "shipping_id": "shipping_id_1",
                        "tracking_number": "tracking_number_1",
                        "shipping_status": "delivered",
                        "shipping_address": {
                            "address": "Street Name",
                            "number": "123",
                            "city": "City",
                            "state": "State",
                            "country": "Country",
                            "comments": "Comment"
                        }
                    }
                }
            ]
        }
    }
}

```

#### Response (ERROR)

1. **No valid credentials found (404 Not Found)**  
   - Occurs when no valid credentials are found for the provided client_id.

```json
{
    "status": "error",
    "message": "No valid credentials found for the provided client_id."
}
```
2. **oken Expired (401 Unauthorized)**  
   - Occurs when the token used for authentication has expired. The user needs to renew their token.

```json
{
    "status": "error",
    "message": "Token has expired. Please renew your token."
}

```
3. **Failed to get user ID (500 Internal Server Error)**  
   - Occurs when there is an issue with obtaining the user ID from the MercadoLibre API.

```json
{
    "status": "error",
    "message": "Could not get user ID. Please validate your token.",
    "error": {
        "error_code": "some_error_code",
        "error_description": "description_of_the_error"
    }
}

```
4. **Failed to connect to MercadoLibre API (500 Internal Server Error)**  
   - Occurs when there is an issue with the connection to the MercadoLibre API.

```json
{
    "status": "error",
    "message": "Error connecting to MercadoLibre API.",
    "error": {
        "error_code": "some_error_code",
        "error_description": "description_of_the_error"
    }
}

```

### Get Sales By Date Range
**Endpoint:** `/api/mercadolibre/reportes/getSalesByDateRangeController`
**Method:** `GET`
**Description:** Retrieves a daily sales summary from MercadoLibre for a specific client_id, allowing users to view sales made within a given date range. The response includes the total sales amount and the products sold.
#### Response (SUCCESS)

```json
{
  "status": "success",
  "message": "Ventas obtenidas con éxito.",
  "data": {
    "2025-01-01": [
      {
        "order_id": "12345",
        "order_date": "2025-01-01T10:00:00.000-00:00",
        "total_amount": 200.50,
        "payment_method": "credit_card",
        "products": [
          {
            "id": "987654321",
            "title": "Producto 1",
            "quantity": 2,
            "price": 100.25,
            "category_id": "MLA12345",
            "category": "Electronics"
          }
        ]
      }
    ]
  }
}

```

#### Response (ERROR)

1. **Token Not Found (404 Not Found)**  
   - Happens when there is no token associated with the given credentials.

```json
{
  "status": "error",
  "message": "No se encontraron credenciales válidas para el client_id proporcionado."
}
```
2. **Token Expired (401 Unauthorized)**  
   - Happens when the token has expired and needs to be renewed.

```json
{
  "status": "error",
  "message": "El token ha expirado. Por favor, renueve su token."
}
```
3. **Invalid Date Range (400 Bad Request)**  
   - Happens when the start_date or end_date is not provided or the dates are invalid.

```json
{
  "status": "error",
  "message": "Las fechas inicial y final son requeridas."
}
```
4. **Error Fetching User ID (500 Internal Server Error)**  
   - Happens when there is an error while fetching the user ID from the MercadoLibre API.

```json
{
  "status": "error",
  "message": "No se pudo obtener el ID del usuario. Por favor, valide su token.",
  "error": { /* error details */ }
}

```
### Get Sales By Month
**Endpoint:** `/api/mercadolibre/reportes/getSalesByMonthController`
**Method:** `GET`
**Description:** Retrieves monthly sales from the MercadoLibre API using the provided client_id. This allows obtaining a summary of all sales for a specific month, including total sales and the products sold.
#### Response (SUCCESS)

```json
{
    {
    "status": "success",
    "message": "Ventas por mes obtenidas con éxito.",
    "data": {
        "2024-01": {
            "total_amount": 1500.75,
            "orders": [
                {
                    "id": 123456789,
                    "date_created": "2024-01-10T14:30:00.000-00:00",
                    "total_amount": 500.25,
                    "status": "paid",
                    "sold_products": [
                        {
                            "order_id": 123456789,
                            "order_date": "2024-01-10T14:30:00.000-00:00",
                            "title": "Smartphone XYZ",
                            "quantity": 1,
                            "price": 500.25
                        }
                    ]
                },
                {
                    "id": 987654321,
                    "date_created": "2024-01-15T10:15:00.000-00:00",
                    "total_amount": 1000.50,
                    "status": "paid",
                    "sold_products": [
                        {
                            "order_id": 987654321,
                            "order_date": "2024-01-15T10:15:00.000-00:00",
                            "title": "Laptop ABC",
                            "quantity": 1,
                            "price": 1000.50
                        }
                    ]
                }
            ]
        }
    }
}

}
```

#### Response (ERROR)

1. **Token Not Found (404 Not Found)**  
   - Happens when there is no token associated with the given credentials.

```json
{
    "status": "error",
    "message": "No se encontraron credenciales válidas para el client_id proporcionado."
}
```
2. **Token Expired (401 Unauthorized)**  
   - Occurs when the access token is expired.

```json
{
    "status": "error",
    "message": "El token ha expirado. Por favor, renueve su token."
}
```
3. **Failed to Retrieve User ID (500 Internal Server Error)**  
   - Happens when the API fails to fetch the user ID due to an invalid or expired token.

```json
{
    "status": "error",
    "message": "No se pudo obtener el ID del usuario. Por favor, valide su token.",
    "error": {}
}

```
4. **Failed to Connect to MercadoLibre API (500 Internal Server Error)**  
   - Occurs when there's an issue connecting to MercadoLibre's API.

```json
{
    "status": "error",
    "message": "Error al conectar con la API de MercadoLibre.",
    "error": {}
}

```
### Get Sales By Week
**Endpoint:** `/api/mercadolibre/reportes/getSalesByWeekController`
**Method:** `GET`
**Description:** Retrieves weekly sales from the MercadoLibre API using the provided client_id, based on a given week start and end date. This allows obtaining a summary of all sales for a specific week, including the total sales amount and details of sold products.

| Parameter      | Type     |Required| Description                                     |
|----------------|----------|--------|-------------------------------------------------|
| `year`         | int      |  No    |Year of the sales data (default: current year)   |
| `client_id`    | int      |  No    |Month of the sales data (default: current month) |
| `client_secret`| String   |  Yes   |Start date of the week (format: YYYY-MM-DD).     |
| `created_at`   | int      |  Yes   |End date of the week (format: YYYY-MM-DD).       |
#### Response (SUCCESS)

```json
{
 {
    "status": "success",
    "message": "Ingresos y productos obtenidos con éxito.",
    "data": {
        "week_start_date": "2024-01-01",
        "week_end_date": "2024-01-07",
        "total_sales": 3200.50,
        "sold_products": [
            {
                "title": "Smartphone XYZ",
                "quantity": 3,
                "total_amount": 1500.75
            },
            {
                "title": "Laptop ABC",
                "quantity": 1,
                "total_amount": 1700.00
            }
        ]
    }
}
   
}
```

#### Response (ERROR)

1. **Missing Dates (400 Bad Request)**  
   - Happens when week_start_date or week_end_date is missing.

```json
{
    "status": "error",
    "message": "Las fechas de la semana son requeridas."
}
```
2. **Token Not Found (404 Not Found)**  
   - Happens when no credentials are found for the provided client_id.

```json
{
    "status": "error",
    "message": "No se encontraron credenciales válidas para el client_id proporcionado."
}
```
3. **Token Expired (401 Unauthorized)**  
   - Occurs when the access token is expired.

```json
{
    "status": "error",
    "message": "El token ha expirado. Por favor, renueve su token."
}
```
4. **Failed to Retrieve User ID (500 Internal Server Error)**  
   - Happens when the API fails to fetch the user ID due to an invalid or expired token.

```json
{
    "status": "error",
    "message": "No se pudo obtener el ID del usuario. Por favor, valide su token.",
    "error": {}
}

```
5. **Failed to Connect to MercadoLibre API (500 Internal Server Error)**  
   - Occurs when there's an issue connecting to MercadoLibre's API.

```json
{
    "status": "error",
    "message": "Error al conectar con la API de MercadoLibre.",
    "error": {}
}
```

### Get Top Payment Methods
**Endpoint:** `/api/mercadolibre/reportes/getTopPaymentMethodsController`
**Method:** `GET`
**Description:** Fetches top payment methods for a specific client ID from MercadoLibre.
#### Response (SUCCESS)

```json
{
    "status": "success",
    "message": "Métodos de pago más utilizados obtenidos con éxito.",
    "request_date": "2025-02-03 15:00:00",
    "data": {
        "credit_card": 150,
        "paypal": 120,
        "mercadopago": 90
    } 
}
```

#### Response (ERROR)

1. **Credentials Not Found (404 Not Found)**  
   - Happens when no credentials are found for the provided client_id.

```json
{
    "status": "error",
    "message": "Credenciales no encontradas."
 
}
```
2. **Token Expired (401 Unauthorized)**  
   - Occurs if the access token associated with the credentials has expired and needs to be renewed.

```json
{
    "status": "error",
    "message": "El token ha expirado. Por favor, renueve su token."
}
```
3. **Failed to Get User ID (500 Internal Server Error)**  
   - This happens if the system fails to retrieve the user ID using the provided access token, possibly due to a token issue.

```json
{
    "status": "error",
    "message": "No se pudo obtener el ID del usuario. Por favor, valide su token.",
    "error": {...}
}
```

### Get Top Selling Products
**Endpoint:** `/api/mercadolibre/reportes/getTopSellingProductsController`
**Method:** `GET`
**Description:** Fetches the top-selling products for a specific client ID from MercadoLibre.
#### Response (SUCCESS)

```json
{
    {
    "status": "success",
    "message": "Productos más vendidos obtenidos con éxito.",
    "total_sales": 35000.00,
    "data": [
        {
            "title": "Producto A",
            "quantity": 120,
            "total_amount": 1800.00
        },
        {
            "title": "Producto B",
            "quantity": 100,
            "total_amount": 1500.00
        },
        {
            "title": "Producto C",
            "quantity": 80,
            "total_amount": 1200.00
        }
    ]
}

}
```

#### Response (ERROR)

1. **Credentials Not Found (404 Not Found)**  
   - Happens when no credentials are found for the provided client_id.

```json
{
    "status": "error",
    "message": "Credenciales no encontradas."
}
```
2. **Token Expired (401 Unauthorized)**  
   - Occurs if the access token associated with the credentials has expired and needs to be renewed.

```json
{
    "status": "error",
    "message": "El token ha expirado. Por favor, renueve su token."
}
```
3. **Failed to Get User ID (500 Internal Server Error)**  
   - This happens if the system fails to retrieve the user ID using the provided access token, possibly due to a token issue.

```json
{
    "status": "error",
    "message": "No se pudo obtener el ID del usuario. Por favor, valide su token.",
    "error": {...}
}

```
4. **Error Fetching Sales Data (500 Internal Server Error)**  
   - Occurs when the request to fetch sales data from MercadoLibre's API fails.

```json
{
    "status": "error",
    "message": "Error al conectar con la API de MercadoLibre.",
    "error": {...}
}
```

### Get Weeks Of Month
**Endpoint:** `/api/mercadolibre/reportes/getWeeksOfMonthController`
**Method:** `GET`
**Description:** Retrieves the weeks of a specific month and year.
#### Response (SUCCESS)

```json
{
    "status": "success",
    "message": "Semanas obtenidas con éxito.",
    "data": [
        {
            "start_date": "2025-02-01",
            "end_date": "2025-02-07"
        },
        {
            "start_date": "2025-02-08",
            "end_date": "2025-02-14"
        },
        {
            "start_date": "2025-02-15",
            "end_date": "2025-02-21"
        },
        {
            "start_date": "2025-02-22",
            "end_date": "2025-02-28"
        }
    ]
}

```

#### Response (ERROR)

1. **Invalid Date (400 Bad Request)**  
   - Occurs when an invalid year or month is provided (for example, a non-existent date).

```json
{
    "status": "error",
    "message": "Fecha no válida. Por favor, proporcione un año y mes válidos."
}
```
2. **Processing Error (500 Internal Server Error)**  
   - Happens when there's an issue while processing the request, such as an exception in the date calculations.

```json
{
    "status": "error",
    "message": "Error al procesar la solicitud.",
    "error": "Detalles del error."
}
```

### Summary
**Endpoint:** `/api/mercadolibre/reportes/summaryController`
**Method:** `GET`
**Description:** Retrieves a general summary of the store's performance, including total sales, top-selling products, order statuses, daily/weekly/monthly/annual sales, and top payment methods.
#### Response (SUCCESS)

```json
{
    "status": "success",
    "message": "Resumen de la tienda obtenido con éxito.",
    "data": {
        "total_sales": 123456.78,
        "top_selling_products": [
            {
                "product_id": "123",
                "product_name": "Producto A",
                "total_sales": 5000
            },
            {
                "product_id": "456",
                "product_name": "Producto B",
                "total_sales": 4000
            },
            {
                "product_id": "789",
                "product_name": "Producto C",
                "total_sales": 3000
            }
        ],
        "order_statuses": [
            {
                "status": "paid",
                "count": 1200
            },
            {
                "status": "shipped",
                "count": 1100
            }
        ],
        "daily_sales": 2500,
        "weekly_sales": 12000,
        "monthly_sales": 50000,
        "annual_sales": 600000,
        "top_payment_methods": [
            {
                "payment_method": "Credit Card",
                "total_sales": 40000
            },
            {
                "payment_method": "PayPal",
                "total_sales": 35000
            },
            {
                "payment_method": "Bank Transfer",
                "total_sales": 25000
            }
        ]
    }
}
```

#### Response (ERROR)

1. **Token Not Found (404 Not Found)**  
   - Occurs when no valid credentials are found for the provided client ID

```json
{
    "status": "error",
    "message": "No se encontraron credenciales válidas para el client_id proporcionado."
}
```
2. **Token Expired (401 Unauthorized)**  
   - Happens when the access token has expired.

```json
{
    "status": "error",
    "message": "El token ha expirado. Por favor, renueve su token."
}
```
3. **Failed to Fetch Data (500 Internal Server Error)**  
   - Occurs if there’s an issue retrieving any of the required data from the MercadoLibre API (e.g., user ID, sales, products, etc.).

```json
{
    "status": "error",
    "message": "No se pudo obtener el ID del usuario. Por favor, valide su token.",
    "error": "Detalles del error"
}
```
4. **General Processing Errors**  
   - May arise during communication with various endpoints for sales, products, payments, etc.

```json
{
    "status": "error",
    "message": "Error al obtener las ventas totales.",
    "error": "Detalles del error"
}
```




---

## Notes
- Ensure all requests include proper authentication.
- API responses are subject to MercadoLibre rate limits.
- For detailed error handling, refer to the MercadoLibre API documentation.