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
            "total_amount": 10780,
            "orders": [
                {
                    "id": 1234567890,
                    "date_created": "2025-01-02T15:23:40.000-04:00",
                    "total_amount": 10780,
                    "status": "paid",
                    "sold_products": [
                        {
                            "order_id": 1234567890,
                            "order_date": "2025-01-02T15:23:40.000-04:00",
                            "title": "Producto Ejemplo",
                            "quantity": 2,
                            "price": 5390
                        }
                    ]
                }
            ]
        }
    }
}
```


### 4. **Get weeks of the month**

**GET** `/mercadolibre/weeks-of-month`

Optional query parameters:
- `year`: The year for which to retrieve sales data (e.g., `2025`).

- `month`: The month for which to retrieve sales data (e.g., `01`).

> Note: You can omit the `year` or `year` and the system will use the current date.

#### Response (Success)
```json
{
    "status": "success",
    "message": "Semanas obtenidas con éxito.",
    "data": [
        {
            "start_date": "2025-01-01",
            "end_date": "2025-01-05"
        },
        {
            "start_date": "2025-01-06",
            "end_date": "2025-01-12"
        },
        {
            "start_date": "2025-01-13",
            "end_date": "2025-01-19"
        },
        {
            "start_date": "2025-01-20",
            "end_date": "2025-01-26"
        },
        {
            "start_date": "2025-01-27",
            "end_date": "2025-01-31"
        }
    ]
}
```

### 5. **Get sales by week**

**GET** `/mercadolibre/sales-by-week/{client_id}`

Optional query parameters:
- `year`: The year for which to retrieve sales data (e.g., `2025`).
- `month`: The month for which to retrieve sales data (e.g., `01`).
- `week_start_date` (required): The start date of the week (e.g., `2025-01-01`).
- `week_end_date` (required): The end date of the week (e.g., `2025-01-07`).

> Note: You can omit the `year` and `month` parameters, and the system will use the current date. However, `week_start_date` and `week_end_date` are required.

#### Response (Success)
```json
{
    "status": "success",
    "message": "Ingresos obtenidos con éxito.",
    "data": {
        "week_start_date": "2025-01-01",
        "week_end_date": "2025-01-07",
        "total_sales": 50000
    }
}
```

