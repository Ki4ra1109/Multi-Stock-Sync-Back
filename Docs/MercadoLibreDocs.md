# MercadoLibre API Integration Documentation
These endpoints are used for MercadoLibre logic in the system, which includes: Logging in/out using OAuth 2.0 and a [MercadoLibre Application](https://developers.mercadolibre.cl/es_ar/crea-una-aplicacion-en-mercado-libre-es), uploading and modifying products, stock management, and other sections.

## Endpoints

### 1. **Login and Save Credentials**
**POST** `/api/mercadolibre/login`

This endpoint validates the `client_id` and `client_secret` provided by the user, saves them in the database, and generates a Mercado Libre OAuth 2.0 authorization URL.

#### Request Body
```json
{
    "client_id": "<your_client_id>",
    "client_secret": "<your_client_secret>"
}
```

#### Response (Success)
```json
{
    "status": "success",
    "message": "Credentials validated. Redirecting to Mercado Libre...",
    "redirect_url": "https://auth.mercadolibre.cl/authorization?response_type=code&client_id=<your_client_id>&redirect_uri=https://<your_backend>/api/mercadolibre/callback"
}
```

#### Response (Error)
```json
{
    "status": "error",
    "message": "Invalid Client ID or Client Secret. Please check your credentials.",
    "error": {
        "message": "invalid_client",
        "status": 400
    }
}
```

---

### 2. **Handle Callback**
**GET** `/api/mercadolibre/callback`

This endpoint handles the callback from Mercado Libre after user authorization. It exchanges the received `code` for access and refresh tokens and stores them in the database.

#### Query Parameters
- `code`: The authorization code provided by Mercado Libre.

#### Response (Success)
```json
{
    "status": "success",
    "message": "Tokens stored successfully."
}
```

#### Response (Error)
```json
{
    "status": "error",
    "message": "Authorization code not found."
}
```

---

### 3. **Test Connection**
**GET** `/api/mercadolibre/test-connection`

This endpoint validates the stored access token by making a request to the Mercado Libre API to fetch user details.

#### Response (Success)
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

#### Response (Error)
```json
{
    "status": "error",
    "message": "Failed to connect to MercadoLibre. Token might be invalid."
}
```

---

### 4. **Logout**
**POST** `/api/mercadolibre/logout`

This endpoint deletes all stored credentials and tokens, effectively logging the user out of the system.

#### Response (Success)
```json
{
    "status": "success",
    "message": "Logged out and all credentials deleted successfully."
}
```

---

## Database Structure

### 1. `mercado_libre_credentials`
| Column         | Type     | Description                     |
|----------------|----------|---------------------------------|
| `id`           | Integer  | Primary Key                     |
| `client_id`    | String   | Mercado Libre Client ID         |
| `client_secret`| String   | Mercado Libre Client Secret     |
| `created_at`   | Timestamp| Record creation timestamp       |
| `updated_at`   | Timestamp| Record update timestamp         |

### 2. `mercado_libre_tokens`
| Column         | Type     | Description                     |
|----------------|----------|---------------------------------|
| `id`           | Integer  | Primary Key                     |
| `access_token` | String   | Access token for API requests   |
| `refresh_token`| String   | Token for refreshing access     |
| `expires_at`   | Timestamp| Expiration time for the token   |
| `created_at`   | Timestamp| Record creation timestamp       |
| `updated_at`   | Timestamp| Record update timestamp         |

---

## Workflow

1. **Save Credentials**
   - Use `/api/mercadolibre/login` to validate and save client credentials.
2. **Redirect to Mercado Libre Authorization**
   - Use the `redirect_url` from the `/login` response to redirect the user to Mercado Libre.
3. **Handle Callback**
   - Mercado Libre redirects to `/api/mercadolibre/callback` with an authorization `code`.
   - The backend exchanges the code for tokens and saves them.
4. **Test Connection**
   - Use `/api/mercadolibre/test-connection` to validate the access token.
5. **Logout**
   - Use `/api/mercadolibre/logout` to delete all stored credentials and tokens.

---

## Environment Variables

Ensure the following environment variables are set in your `.env` file:

```env
MERCADO_LIBRE_REDIRECT_URI=https://<your_backend>/api/
```

---

## Notes
- Ensure the `MERCADO_LIBRE_REDIRECT_URI` is registered in your Mercado Libre application.
- Tokens are automatically refreshed if needed during API interactions.
