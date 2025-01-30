<?php

namespace App\Http\Controllers\MercadoLibre\Reportes;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class MercadoLibreDocumentsController extends Controller
{
    /**
     * Get invoice report from MercadoLibre API using client_id.
    */

    public function getInvoiceReport($clientId)
    {
        // Get credentials by client_id
        $credentials = MercadoLibreCredential::where('client_id', $clientId)->first();

        // Check if credentials exist
        if (!$credentials) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se encontraron credenciales válidas para el client_id proporcionado.',
            ], 404);
        }

        // Check if token is expired
        if ($credentials->isTokenExpired()) {
            return response()->json([
                'status' => 'error',
                'message' => 'El token ha expirado. Por favor, renueve su token.',
            ], 401);
        }

        // Get query parameters
        $group = request()->query('group', 'MP'); // Default group to 'MP'
        $documentType = request()->query('document_type', 'BILL'); // Default document type to 'BILL'
        $offset = request()->query('offset', 1); // Default offset to 1
        $limit = request()->query('limit', 2); // Default limit to 2

        // API request to get invoice report
        $response = Http::withToken($credentials->access_token)
            ->get('https://api.mercadolibre.com/billing/integration/monthly/periods', [
                'group' => $group,
                'document_type' => $documentType,
                'offset' => $offset,
                'limit' => $limit,
                'site_id' => 'MLC', // Ensure the site is set to Chile
            ]);

        // Validate response
        if ($response->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al conectar con la API de MercadoLibre.',
                'error' => $response->json(),
            ], $response->status());
        }

        // Return invoice report data
        return response()->json([
            'status' => 'success',
            'message' => 'Reporte de facturas obtenido con éxito.',
            'data' => $response->json(),
        ]);
    }
}