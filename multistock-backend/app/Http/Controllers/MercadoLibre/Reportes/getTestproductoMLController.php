<?php
// app/Http/Controllers/MercadoLibre/Reportes/getTestproductoMLController.php

namespace App\Http\Controllers\MercadoLibre\Reportes;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\Controller;

class getTestproductoMLController extends Controller
{
    /**
     * Trae el detalle completo de un producto por item_id o por seller_sku (query param).
     * Si recibe seller_sku, busca el item_id y luego trae el detalle.
     * Si recibe item_id, trae el detalle directamente.
     */
    public function getItemFull(Request $request, $client_id, $item_id = null)
    {
        // Cachear credenciales por 10 minutos
        $cacheKey = 'ml_credentials_' . $client_id;
        $credentials = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($client_id) {
            Log::info("Consultando credenciales Mercado Libre en MySQL para client_id: $client_id");
            return \App\Models\MercadoLibreCredential::where('client_id', $client_id)->first();
        });

        if (!$credentials) {
            return response()->json(['error' => 'Credenciales no encontradas'], 404);
        }

        $accessToken = $credentials->access_token;
        $userId = $credentials->user_id;

        $seller_sku = $request->query('seller_sku');
        $q = $request->query('q'); // texto de búsqueda

        // 1. Buscar por seller_sku
        if ($seller_sku) {
            $response = Http::withToken($accessToken)
                ->get("https://api.mercadolibre.com/users/{$userId}/items/search", [
                    'seller_sku' => $seller_sku
                ]);
            $data = $response->json();
            if (empty($data['results'])) {
                return response()->json(['message' => 'No se encontraron ítems para ese SKU'], 404);
            }
            $item_id = $data['results'][0];
        }

        // 2. Buscar por texto si no hay item_id y viene q
        if (!$item_id && $q) {
            $response = Http::get("https://api.mercadolibre.com/sites/MLC/search", [
                'q' => $q
            ]);
            $data = $response->json();
            if (empty($data['results'])) {
                return response()->json(['message' => 'No se encontraron ítems para ese texto'], 404);
            }
            $item_id = $data['results'][0]['id'];
        }

        if (!$item_id) {
            return response()->json(['error' => 'Debes enviar seller_sku, item_id o q (texto de búsqueda)'], 400);
        }

        // Traer el producto padre con todas sus variaciones
        $response = Http::withToken($accessToken)
            ->get("https://api.mercadolibre.com/items/{$item_id}");

        if ($response->failed()) {
            return response()->json(['error' => 'No se encontró el producto'], 404);
        }

        $item = $response->json();

        // Agrupar variantes por color y diseño, listando tallas y sumando stock
        $agrupadas = [];
        if (!empty($item['variations'])) {
            foreach ($item['variations'] as $variation) {
                $color = null;
                $diseno = null;
                $talla = null;
                $stock = $variation['available_quantity'] ?? 0;
                $sku = $variation['seller_sku'] ?? null;
                $ean = null;

                // Buscar color, diseño, talla y EAN en attribute_combinations y attributes
                if (!empty($variation['attribute_combinations'])) {
                    foreach ($variation['attribute_combinations'] as $attr) {
                        if ($attr['id'] === 'COLOR') {
                            $color = $attr['value_name'];
                        }
                        if ($attr['id'] === 'FABRIC_DESIGN') {
                            $diseno = $attr['value_name'];
                        }
                        if ($attr['id'] === 'SIZE') {
                            $talla = $attr['value_name'];
                        }
                    }
                }
                // Buscar EAN/GTIN en attributes
                if (!empty($variation['attributes'])) {
                    foreach ($variation['attributes'] as $attr) {
                        if (in_array($attr['id'], ['EAN', 'GTIN', 'UPC'])) {
                            $ean = $attr['value_name'];
                        }
                    }
                }

                $key = $color . ' / ' . $diseno;
                if (!isset($agrupadas[$key])) {
                    $agrupadas[$key] = [
                        'color' => $color,
                        'diseno' => $diseno,
                        'tallas' => [],
                        'stock_total' => 0,
                        'variation_ids' => [],
                        'pictures' => [],
                    ];
                }
                $agrupadas[$key]['tallas'][] = $variation;
                $agrupadas[$key]['stock_total'] += $stock;
                $agrupadas[$key]['variation_ids'][] = $variation['id'];
                if (!empty($variation['picture_ids'])) {
                    $agrupadas[$key]['pictures'] = $variation['picture_ids'];
                }

                // Log de advertencia si falta seller_sku
                if (empty($variation['seller_sku'])) {
                    Log::warning("Variación sin seller_sku", [
                        'variation_id' => $variation['id'] ?? null,
                        'variation' => $variation
                    ]);
                } else {
                    $processedVariation['seller_sku'] = $variation['seller_sku'];
                }
            }
        }

        // Convertir a array indexado para la respuesta
        $variantes = array_values($agrupadas);

        // 2. Modificar seller_sku en cada variación (puedes usar tu lógica para generar el SKU)
        foreach ($item['variations'] as &$variation) {
            // Ejemplo: SKU compuesto
            $color = null;
            $talla = null;
            if (!empty($variation['attribute_combinations'])) {
                foreach ($variation['attribute_combinations'] as $attr) {
                    if ($attr['id'] === 'COLOR') $color = $attr['value_name'];
                    if ($attr['id'] === 'SIZE') $talla = $attr['value_name'];
                }
            }
            $sku_compuesto = 'MLC_' . $item['id'] . '-VAR_' . $variation['id'];
            if ($color) $sku_compuesto .= '-COLOR_' . $color;
            if ($talla) $sku_compuesto .= '-TALLA_' . $talla;

            // Buscar o agregar el atributo SELLER_SKU
            $found = false;
            if (!empty($variation['attributes'])) {
                foreach ($variation['attributes'] as &$attr) {
                    if ($attr['id'] === 'SELLER_SKU') {
                        $attr['value_name'] = $sku_compuesto;
                        $found = true;
                        break;
                    }
                }
            } else {
                $variation['attributes'] = [];
            }
            if (!$found) {
                $variation['attributes'][] = [
                    'id' => 'SELLER_SKU',
                    'name' => 'SKU',
                    'value_id' => null,
                    'value_name' => $sku_compuesto
                ];
            }

            // Eliminar catalog_product_id si existe, aunque sea null
            if (array_key_exists('catalog_product_id', $variation)) {
                unset($variation['catalog_product_id']);
            }
        }
        unset($variation); // Limpia la referencia

        Log::info('Variations antes de PUT', $item['variations']);
        // 3. Enviar el array completo de variaciones actualizado
        $updateResponse = Http::withToken($accessToken)
            ->put("https://api.mercadolibre.com/items/{$item_id}", [
                'variations' => $item['variations']
            ]);

        if ($updateResponse->failed()) {
            return response()->json(['error' => 'Error al actualizar variaciones', 'details' => $updateResponse->json()], 400);
        }

        // Devuelve el producto con sus variaciones actualizadas
        return response()->json([
            'item' => $item,
            'variantes_agrupadas' => $variantes // Opcional: agrupaciones por color/diseño/talla
        ]);
    }
}