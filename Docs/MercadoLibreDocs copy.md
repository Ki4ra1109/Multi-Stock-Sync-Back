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
   - [Get Daily Sales](#get-Daily-Sales)
   - [Get Invoice Report](#get-Invoice-Report)
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
### Get All Credentials Data
**Endpoint:** `/api/mercadolibre/credentials/getAllCredentialsController`
**Method:** `GET`
**Description:** Retrieves all stored MercadoLibre credentials.
#### Response (SUCCESS)

```json
{
    
    "Status": "success",
            "data": "$credentials",
    
}
```

#### Response (ERROR)

```json
{
    "status": "error",
                "message": "Error al conectar con MercadoLibre. El token podría ser inválido."
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
### Handle Callback
**Endpoint:** `/api/mercadolibre/login/callback`
**Method:** `POST`
**Description:** Handles the MercadoLibre OAuth callback.
**Response:** Authentication confirmation.

### Login
**Endpoint:** `/api/mercadolibre/login`
**Method:** `POST`
**Description:** Initiates the login process with MercadoLibre.
**Response:** Redirect URL for authentication.

## Products
### Get Product Reviews
**Endpoint:** `/api/mercadolibre/products/reviews`
**Method:** `GET`
**Description:** Fetches reviews for a product.
**Response:** List of reviews.

### List Products by Client ID
**Endpoint:** `/api/mercadolibre/products/list/{clientId}`
**Method:** `GET`
**Description:** Lists products associated with a given client ID.
**Response:** List of products.

### Save Products
**Endpoint:** `/api/mercadolibre/products/save`
**Method:** `POST`
**Description:** Saves a new product to MercadoLibre.
**Response:** Confirmation message.

### Search Products
**Endpoint:** `/api/mercadolibre/products/search`
**Method:** `GET`
**Description:** Searches for products on MercadoLibre.
**Response:** List of matching products.

## Reports
### Compare Annual Sales Data
**Endpoint:** `/api/mercadolibre/reports/compare-annual-sales`
**Method:** `GET`
**Description:** Compares annual sales data.
**Response:** JSON object with comparative sales data.

### Compare Sales Data
**Endpoint:** `/api/mercadolibre/reports/compare-sales`
**Method:** `GET`
**Description:** Compares general sales data.
**Response:** JSON object with sales metrics.

### Get Annual Sales
**Endpoint:** `/api/mercadolibre/reports/annual-sales`
**Method:** `GET`
**Description:** Retrieves annual sales reports.
**Response:** List of annual sales data.

## Get Daily Sales
**Endpoint:**
**Method:**
**Description:**
**Response:**

---

## Notes
- Ensure all requests include proper authentication.
- API responses are subject to MercadoLibre rate limits.
- For detailed error handling, refer to the MercadoLibre API documentation.

