<?php

namespace App\Http\Controllers\MercadoLibre\Products;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class getAtributosCategoriaController extends Controller
{
    public function getAtributos(Request $request, $id)
    {
        $client_id = $request->query('client_id');

        // Cachear credenciales por 10 minutos
        $cacheKey = 'ml_credentials_' . $client_id;
        $cred = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($client_id) {
            Log::info("Consultando credenciales Mercado Libre en MySQL para client_id: $client_id");
            return \App\Models\MercadoLibreCredential::where('client_id', $client_id)->first();
        });

        if (!$cred) {
            return response()->json(['error' => 'Token inválido o expirado'], 401);
        }

        if ($cred->isTokenExpired()) {
            $refreshResponse = Http::asForm()->post('https://api.mercadolibre.com/oauth/token', [
                'grant_type' => 'refresh_token',
                'client_id' => $cred->client_id,
                'client_secret' => $cred->client_secret,
                'refresh_token' => $cred->refresh_token,
            ]);

            if ($refreshResponse->failed()) {
                return response()->json(['error' => 'No se pudo refrescar el token'], 401);
            }

            $data = $refreshResponse->json();
            $cred->update([
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'],
                'expires_at' => now()->addSeconds($data['expires_in']),
            ]);
        }

        // Consulta a Mercado Libre con el token actualizado
        $response = Http::withToken($cred->access_token)
            ->get("https://api.mercadolibre.com/categories/{$id}/attributes");

        if ($response->failed()) {
            return response()->json(['error' => 'Error al obtener atributos'], $response->status());
        }

        $responseData = $response->json();

        // Filtrar los atributos
        $filteredAttributes = [];
        $sizeGridAttribute = null;

        // Si la respuesta es directamente un array de atributos
        $attributes = is_array($responseData) ? $responseData : ($responseData['attributes'] ?? []);

        foreach ($attributes as $attribute) {
            // Verificar si es el atributo size_grid_id
            if ($attribute['id'] === 'SIZE_GRID_ID') {
                // Crear un array con solo los campos necesarios para SIZE_GRID_ID
                $filteredAttribute = [
                    'id' => $attribute['id'],
                    'name' => $attribute['name'],
                    'tags' => $attribute['tags'] ?? [],
                    'value_type' => $attribute['value_type'] ?? null
                ];

                // Agregar values solo si existe
                if (isset($attribute['values']) && !empty($attribute['values'])) {
                    $filteredAttribute['values'] = $attribute['values'];
                }

                continue; // Saltar al siguiente atributo
            }

            // Verificar si cumple con los criterios de filtrado dentro del objeto tags
            $tags = $attribute['tags'] ?? [];
            $isRequired = isset($tags['required']) && $tags['required'] === true;
            $isCatalogRequired = isset($tags['catalog_required']) && $tags['catalog_required'] === true;

            // Solo agregar si cumple con alguno de los criterios (required O catalog_required)
            if ($isRequired || $isCatalogRequired) {
                // Determinar el value_type apropiado
                $valueType = $attribute['value_type'] ?? null;

                // Cambiar value_type a "list" para COLOR y BRAND
                if (in_array($attribute['id'], ['COLOR', 'BRAND'])) {
                    $valueType = 'list';
                }

                // Crear un array con solo los campos necesarios
                $filteredAttribute = [
                    'id' => $attribute['id'],
                    'name' => $attribute['name'],
                    'tags' => $attribute['tags'] ?? [],
                    'value_type' => $valueType
                ];

                // Agregar values solo si existe
                if (isset($attribute['values']) && !empty($attribute['values'])) {
                    $filteredAttribute['values'] = $attribute['values'];
                }

                $filteredAttributes[] = $filteredAttribute;
            }
        }

        // Crear respuesta filtrada
        $filteredResponse = [
            'category_id' => $id,
            'filtered_attributes' => $filteredAttributes,
            'has_size_grid' => $sizeGridAttribute !== null,
            'total_filtered' => count($filteredAttributes),
            'filter_criteria' => [
                'tags.required' => true,
                'tags.catalog_required' => true,
                'condition' => 'OR' // Indica que se incluyen atributos que cumplan required O catalog_required
            ]
        ];

        return response()->json($filteredResponse, 200);
    }
}
