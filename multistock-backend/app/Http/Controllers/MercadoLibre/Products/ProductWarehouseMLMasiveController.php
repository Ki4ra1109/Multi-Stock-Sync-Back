<?php

namespace App\Http\Controllers\MercadoLibre\Products;

use App\Http\Controllers\Controller;
use App\Models\MercadoLibreCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ProductWarehouseMLMasiveController extends Controller
{
    public function DescargarPlantillaML($clientId, $categoryId)
    {
        $credentials = MercadoLibreCredential::where('client_id', $clientId)->first();

        if (!$credentials) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se encontraron credenciales válidas para el client_id proporcionado.',
            ], 404);
        }

        if ($credentials->isTokenExpired()) {
            return response()->json([
                'status' => 'error',
                'message' => 'El token ha expirado. Por favor, renueve su token.',
            ], 401);
        }

        // Obtener plantilla de Excel desde Mercado Libre
        $response = Http::withToken($credentials->access_token)
            ->get('https://api.mercadolibre.com/catalog_templates', [
                'category_id' => $categoryId
            ]);

        if ($response->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo obtener la plantilla desde Mercado Libre.',
                'ml_error' => $response->json(),
            ], $response->status());
        }

        $data = $response->json();

        if (empty($data['templates'][0]['file_url'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'No hay plantilla disponible para la categoría indicada.',
            ], 404);
        }

        $fileUrl = $data['templates'][0]['file_url'];

        // Descargar archivo .xlsx
        $fileResponse = Http::get($fileUrl);

        if ($fileResponse->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo descargar el archivo de plantilla.',
            ], 500);
        }

        return response($fileResponse->body(), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="plantilla_' . $categoryId . '.xlsx"',
        ]);
    }
}
