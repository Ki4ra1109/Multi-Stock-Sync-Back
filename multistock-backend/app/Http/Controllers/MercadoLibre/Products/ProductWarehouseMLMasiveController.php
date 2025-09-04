<?php

namespace App\Http\Controllers\MercadoLibre\Products;

use App\Http\Controllers\Controller;
use App\Models\MercadoLibreCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProductWarehouseMLMasiveController extends Controller
{
    public function SubirPlantillaML(Request $request, $clientId)
    {
        $request->validate([
            'file' => 'required|file|mimes:xls,xlsx,csv',
            'template_id' => 'required|string'
        ]);

        // Cachear credenciales por 10 minutos
        $cacheKey = 'ml_credentials_' . $clientId;
        $credentials = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($clientId) {
            Log::info("Consultando credenciales Mercado Libre en MySQL para client_id: $clientId");
            return MercadoLibreCredential::where('client_id', $clientId)->first();
        });

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

        $file = $request->file('file');
        $path = $file->getPathname();
        $filename = $file->getClientOriginalName();

        $templateId = $request->input('template_id');

        $response = Http::withToken($credentials->access_token)
            ->attach('file', file_get_contents($path), $filename)
            ->post('https://api.mercadolibre.com/catalog/listings/bulk_upload', [
                'template_id' => $templateId,
            ]);

        if ($response->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al subir el archivo a Mercado Libre.',
                'ml_error' => $response->json(),
            ], $response->status());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Archivo subido exitosamente a Mercado Libre.',
            'data' => $response->json(),
        ]);
    }
}
