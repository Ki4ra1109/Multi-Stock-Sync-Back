<?php

namespace App\Http\Controllers\MercadoLibre\Products;

use App\Models\Product;
use App\Models\MercadoLibreCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class itemController
{
    /**
     * Crear un nuevo producto en MercadoLibre y guardarlo en la base de datos.
     */
    public function store(Request $request)
    {
        $request->validate([
            'client_id'   => 'required|string',
            'title'       => 'required|string',
            'price'       => 'required|numeric',
            'stock'       => 'required|integer',
            'sku'         => 'required|string|unique:products,sku',
            'category_id' => 'required|string',
            'size'        => 'nullable|string',
            'description' => 'nullable|string',
            'images.*'    => 'image|mimes:jpeg,png,jpg,gif',
            'image_urls.*'=> 'url',
        ]);

        // Cachear credenciales por 10 minutos
        $cacheKey = 'ml_credentials_' . $request->client_id;
        $credentials = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($request) {
            Log::info("Consultando credenciales Mercado Libre en MySQL para client_id: {$request->client_id}");
            return MercadoLibreCredential::where('client_id', $request->client_id)->first();
        });

        if (!$credentials || $credentials->isTokenExpired()) {
            return response()->json(['status' => 'error', 'message' => 'Credenciales no válidas o token expirado.'], 401);
        }

        // Procesar imágenes (subir locales y combinar con URLs)
        $imageUrls = $request->image_urls ?? [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('products', 'public');
                $imageUrls[] = asset("storage/$path");
            }
        }

        // Datos del producto para MercadoLibre
        $productData = [
            'title'        => $request->title,
            'category_id'  => $request->category_id,
            'price'        => $request->price,
            'currency_id'  => 'CLP',
            'available_quantity' => $request->stock,
            'buying_mode'  => 'buy_it_now',
            'listing_type_id' => 'gold_special',
            'description'  => ['plain_text' => $request->description ?? ''],
            'pictures'     => array_map(fn($url) => ['source' => $url], $imageUrls),
            'attributes'   => [
                ['id' => 'SIZE', 'value_name' => $request->size ?? ''],
            ]
        ];

        // Publicar en MercadoLibre
        $response = Http::withToken($credentials->access_token)
            ->post('https://api.mercadolibre.com/items', $productData);

        if ($response->failed()) {
            return response()->json(['status' => 'error', 'message' => 'Error al publicar en MercadoLibre.', 'error' => $response->json()], $response->status());
        }

        $mlProduct = $response->json();

        // Guardar en la base de datos
        $product = Product::create([
            'ml_id'       => $mlProduct['id'],
            'client_id'   => $request->client_id,
            'title'       => $request->title,
            'price'       => $request->price,
            'stock'       => $request->stock,
            'sku'         => $request->sku,
            'category_id' => $request->category_id,
            'size'        => $request->size,
            'description' => $request->description,
            'images'      => json_encode($imageUrls),
        ]);

        return response()->json(['status' => 'success', 'message' => 'Producto creado exitosamente.', 'data' => $product]);
    }

    /**
     * Editar un producto en MercadoLibre y actualizarlo en la base de datos.
     */
    public function update(Request $request, $item_id)
    {
        $request->validate([
            'client_id'   => 'required|string',
            'title'       => 'sometimes|string',
            'price'       => 'sometimes|numeric',
            'stock'       => 'sometimes|integer',
            'category_id' => 'sometimes|string',
            'size'        => 'nullable|string',
            'description' => 'nullable|string',
            'images.*'    => 'image|mimes:jpeg,png,jpg,gif',
            'image_urls.*'=> 'url',
        ]);

        // Cachear credenciales por 10 minutos
        $cacheKey = 'ml_credentials_' . $request->client_id;
        $credentials = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($request) {
            Log::info("Consultando credenciales Mercado Libre en MySQL para client_id: {$request->client_id}");
            return MercadoLibreCredential::where('client_id', $request->client_id)->first();
        });

        if (!$credentials || $credentials->isTokenExpired()) {
            return response()->json(['status' => 'error', 'message' => 'Credenciales no válidas o token expirado.'], 401);
        }

        // Buscar producto en la base de datos
        $product = Product::where('ml_id', $item_id)->first();
        if (!$product) {
            return response()->json(['status' => 'error', 'message' => 'Producto no encontrado en el sistema.'], 404);
        }

        // Procesar imágenes (subir locales y combinar con URLs)
        $imageUrls = $request->image_urls ?? json_decode($product->images, true);
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('products', 'public');
                $imageUrls[] = asset("storage/$path");
            }
        }

        // Datos del producto para MercadoLibre
        $productData = array_filter([
            'title'         => $request->title ?? $product->title,
            'price'         => $request->price ?? $product->price,
            'available_quantity' => $request->stock ?? $product->stock,
            'category_id'   => $request->category_id ?? $product->category_id,
            'description'   => $request->description ? ['plain_text' => $request->description] : null,
            'pictures'      => array_map(fn($url) => ['source' => $url], $imageUrls),
            'attributes'    => [
                ['id' => 'SIZE', 'value_name' => $request->size ?? $product->size ?? ''],
            ]
        ]);

        // Actualizar en MercadoLibre
        $response = Http::withToken($credentials->access_token)
            ->put("https://api.mercadolibre.com/items/{$item_id}", $productData);

        if ($response->failed()) {
            return response()->json(['status' => 'error', 'message' => 'Error al actualizar en MercadoLibre.', 'error' => $response->json()], $response->status());
        }

        // Actualizar en la base de datos
        $product->update(array_filter([
            'title'       => $request->title,
            'price'       => $request->price,
            'stock'       => $request->stock,
            'category_id' => $request->category_id,
            'size'        => $request->size,
            'description' => $request->description,
            'images'      => json_encode($imageUrls),
        ]));

        return response()->json(['status' => 'success', 'message' => 'Producto actualizado correctamente.', 'data' => $product]);
    }
}
