# MercadoLibre API Reports Documentation
These endpoints are used to generate JSON reports on sales, products, profits, customer reviews, among other products.

However, these endpoints will only be used to generate reports in CSV or PDF format.

## Endpoints

### 1. **Get invoice report** (WIP)

**GET** `/mercadolibre/invoices/{client_id}`

> Note: For this endpoint to work, we must verify that there is a "client_id" with the value we send in the request registered in the system's database.

#### Response (Success)
```json
{
    "status": "success",
    "message": "Reporte de facturas obtenido con éxito.",
    "data": {
        "offset": 1,
        "limit": 2,
        "total": 0,
        "last_id": 0,
        "results": [],
        "errors": []
    }
}
```

### 2. **Get sales by month** (WIP)

**GET** `/mercadolibre/sales-by-month/{client_id}`

Optional query parameters:
- `month`: The month for which to retrieve sales data (e.g., `09` for September).
- `year`: The year for which to retrieve sales data (e.g., `2024`).

> Note: You can omit the `year` and `month` parameters, and the system will use the current date.

#### Response (Success)
```json
{
    "status": "success",
    "message": "Ventas por mes obtenidas con éxito.",
    "data": {
        "2025-01": {
            "total_amount": 90390,
            "orders": [
                {
                    "id": 1234567890123456,
                    "date_created": "2025-01-02T15:23:40.000-04:00",
                    "total_amount": 10780,
                    "status": "paid"
                },
                {
                    "id": 2345678901234567,
                    "date_created": "2025-01-02T15:23:40.000-04:00",
                    "total_amount": 10780,
                    "status": "paid"
                }
                // more sales here...
            ]
        }
    }
}
```

### 3. **Get annual sales**

**GET** `/mercadolibre/annual-sales/{client_id}`

Optional query parameters:
- `year`: The year for which to retrieve sales data (e.g., `2025`).

> Note: You can omit the `year` and the system will use the current date.

#### Response (Success)
```json
{
    "status": "success",
    "message": "Ventas anuales obtenidas con éxito.",
    "data": {
        "2025-01": {
            "total_amount": 90390,
            "orders": [
                {
                    "id": 1000000000000001,
                    "date_created": "2025-01-02T15:23:40.000-04:00",
                    "total_amount": 10780,
                    "status": "paid"
                },
                {
                    "id": 1000000000000002,
                    "date_created": "2025-01-03T21:36:33.000-04:00",
                    "total_amount": 15180,
                    "status": "paid"
                },
                {
                    "id": 1000000000000003,
                    "date_created": "2025-01-05T08:56:09.000-04:00",
                    "total_amount": 9690,
                    "status": "paid"
                }
            ]
        }
    }
}
```


