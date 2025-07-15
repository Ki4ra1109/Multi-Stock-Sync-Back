<?php

namespace App\Http\Controllers\Woocommerce;

use App\Models\WooStore;
use Automattic\WooCommerce\Client;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class WooProductController extends Controller
{
    public function __construct()
    {
        set_time_limit(300);
        ini_set('max_execution_time', 300);
        $this->middleware('auth:sanctum');
    }

    private function connect($storeId)
    {
        $store = WooStore::findOrFail($storeId);

        if (!$store->active) {
            throw new \Exception('Tienda desactivada');
        }

        return new Client(
            $store->store_url,
            $store->consumer_key,
            $store->consumer_secret,
            [
                'version' => 'wc/v3',
                'verify_ssl' => false
            ]
        );
    }

    public function createProduct(Request $request, $storeId)
    {
        try {
            $woocommerce = $this->connect($storeId);

            // Validación de datos de entrada
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'type' => 'sometimes|in:simple,grouped,external,variable',
                'regular_price' => 'sometimes|numeric|min:0',
                'sale_price' => 'sometimes|numeric|min:0',
                'description' => 'sometimes|string',
                'short_description' => 'sometimes|string',
                'sku' => 'sometimes|string|max:100',
                'manage_stock' => 'sometimes|boolean',
                'stock_quantity' => 'sometimes|integer|min:0',
                'stock_status' => 'sometimes|in:instock,outofstock,onbackorder',
                'weight' => 'sometimes|numeric|min:0',
                'dimensions.length' => 'sometimes|numeric|min:0',
                'dimensions.width' => 'sometimes|numeric|min:0',
                'dimensions.height' => 'sometimes|numeric|min:0',
                'categories' => 'sometimes|array',
                'categories.*.id' => 'required_with:categories|integer',
                'tags' => 'sometimes|array',
                'tags.*.id' => 'required_with:tags|integer',
                'images' => 'sometimes|array',
                'images.*.src' => 'required_with:images|url',
                'attributes' => 'sometimes|array',
                'status' => 'sometimes|in:draft,pending,private,publish',
                'featured' => 'sometimes|boolean',
                'catalog_visibility' => 'sometimes|in:visible,catalog,search,hidden',
                'virtual' => 'sometimes|boolean',
                'downloadable' => 'sometimes|boolean',
                'external_url' => 'sometimes|url',
                'button_text' => 'sometimes|string|max:255',
                'tax_status' => 'sometimes|in:taxable,shipping,none',
                'tax_class' => 'sometimes|string',
                'reviews_allowed' => 'sometimes|boolean',
                'purchase_note' => 'sometimes|string',
                'menu_order' => 'sometimes|integer',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Error de validación.',
                    'errors' => $validator->errors(),
                    'status' => 'error'
                ], 422);
            }

            $data = $validator->validated();

            // Campos por defecto para un nuevo producto
            $productData = array_merge([
                'type' => 'simple',
                'status' => 'publish',
                'featured' => false,
                'catalog_visibility' => 'visible',
                'manage_stock' => false,
                'stock_status' => 'instock',
                'virtual' => false,
                'downloadable' => false,
                'reviews_allowed' => true,
                'tax_status' => 'taxable',
            ], $data);

            // Validaciones adicionales basadas en el tipo de producto
            $validationErrors = $this->validateProductType($productData);
            if (!empty($validationErrors)) {
                return response()->json([
                    'message' => 'Error de validación específica del tipo de producto.',
                    'errors' => $validationErrors,
                    'status' => 'error'
                ], 422);
            }

            // Crear el producto en WooCommerce
            $created = $woocommerce->post("products", $productData);

            // Respuesta filtrada con información relevante
            $filtered = $this->filterProductResponse($created, $woocommerce);

            return response()->json([
                'message' => 'Producto creado correctamente.',
                'created_product' => $filtered,
                'status' => 'success'
            ], 201);
        } catch (\Automattic\WooCommerce\HttpClient\HttpClientException $e) {
            return response()->json([
                'message' => 'Error de WooCommerce API.',
                'error' => $e->getMessage(),
                'status' => 'error'
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear el producto.',
                'error' => $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }

    public function updateProduct(Request $request, $storeId, $productId)
    {
        try {
            $woocommerce = $this->connect($storeId);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255',
                'regular_price' => 'sometimes|numeric|min:0',
                'sale_price' => 'sometimes|numeric|min:0',
                'description' => 'sometimes|string',
                'short_description' => 'sometimes|string',
                'sku' => 'sometimes|string|max:100',
                'manage_stock' => 'sometimes|boolean',
                'stock_quantity' => 'sometimes|integer|min:0',
                'stock_status' => 'sometimes|in:instock,outofstock,onbackorder',
                'weight' => 'sometimes|numeric|min:0',
                'dimensions.length' => 'sometimes|numeric|min:0',
                'dimensions.width' => 'sometimes|numeric|min:0',
                'dimensions.height' => 'sometimes|numeric|min:0',
                'categories' => 'sometimes|array',
                'categories.*.id' => 'required_with:categories|integer',
                'tags' => 'sometimes|array',
                'tags.*.id' => 'required_with:tags|integer',
                'images' => 'sometimes|array',
                'images.*.src' => 'required_with:images|url',
                'status' => 'sometimes|in:draft,pending,private,publish',
                'featured' => 'sometimes|boolean',
                'catalog_visibility' => 'sometimes|in:visible,catalog,search,hidden',
                'virtual' => 'sometimes|boolean',
                'downloadable' => 'sometimes|boolean',
                'external_url' => 'sometimes|url',
                'button_text' => 'sometimes|string|max:255',
                'tax_status' => 'sometimes|in:taxable,shipping,none',
                'reviews_allowed' => 'sometimes|boolean',
                'purchase_note' => 'sometimes|string',
                'menu_order' => 'sometimes|integer',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Error de validación.',
                    'errors' => $validator->errors(),
                    'status' => 'error'
                ], 422);
            }

            $data = $validator->validated();

            if (empty($data)) {
                return response()->json([
                    'message' => 'Debes enviar al menos un campo a modificar.',
                    'status' => 'error'
                ], 422);
            }

            $updated = $woocommerce->put("products/{$productId}", $data);

            $filtered = $this->filterProductResponse($updated, $woocommerce);

            return response()->json([
                'message' => 'Producto actualizado correctamente.',
                'updated_product' => $filtered,
                'status' => 'success'
            ]);
        } catch (\Automattic\WooCommerce\HttpClient\HttpClientException $e) {
            return response()->json([
                'message' => 'Error de WooCommerce API.',
                'error' => $e->getMessage(),
                'status' => 'error'
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar el producto.',
                'error' => $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }

    public function getProduct($storeId, $productId)
    {
        try {
            $woocommerce = $this->connect($storeId);

            $product = $woocommerce->get("products/{$productId}");

            return response()->json([
                'product' => $this->filterProductResponse($product, $woocommerce),
                'status' => 'success'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener el producto.',
                'error' => $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }

    public function listProducts($storeId, Request $request)
    {
        try {
            $woocommerce = $this->connect($storeId);

            // Parámetros de consulta
            $params = [
                'per_page' => $request->get('per_page', 10),
                'page' => $request->get('page', 1),
                'search' => $request->get('search'),
                'status' => $request->get('status'),
                'featured' => $request->get('featured'),
                'category' => $request->get('category'),
                'tag' => $request->get('tag'),
                'sku' => $request->get('sku'),
                'orderby' => $request->get('orderby', 'date'),
                'order' => $request->get('order', 'desc'),
            ];

            // Filtrar parámetros vacíos
            $params = array_filter($params, function ($value) {
                return $value !== null && $value !== '';
            });

            $products = $woocommerce->get('products', $params);


            if (!is_array($products)) {
                $products = [$products];
            }

            $filtered = array_map(function ($product) use ($woocommerce) {
                return $this->filterProductResponse($product, $woocommerce);
            }, $products);

            return response()->json([
                'products' => $filtered,
                'status' => 'success'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al listar productos.',
                'error' => $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }

    public function deleteProduct($storeId, $productId)
    {
        try {
            $woocommerce = $this->connect($storeId);

            $deleted = $woocommerce->delete("products/{$productId}", ['force' => true]);

            return response()->json([
                'message' => 'Producto eliminado correctamente.',
                'deleted_product' => $this->filterProductResponse($deleted, $woocommerce),
                'status' => 'success'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar el producto.',
                'error' => $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }
    public function listVariations($storeId, $productId)
    {
        try {
            $woocommerce = $this->connect($storeId);

            $variations = $woocommerce->get("products/{$productId}/variations");

            return response()->json([
                'variations' => $variations,
                'status' => 'success'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener las variaciones.',
                'error' => $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }

    public function createVariation(Request $request, $storeId, $productId)
    {
        try {
            $woocommerce = $this->connect($storeId);

            $validator = Validator::make($request->all(), [
                'regular_price' => 'required|numeric|min:0',
                'sale_price' => 'sometimes|numeric|min:0',
                'attributes' => 'required|array',
                'attributes.*.name' => 'required|string',
                'attributes.*.option' => 'required|string',
                'sku' => 'sometimes|string|max:100',
                'stock_quantity' => 'sometimes|integer|min:0',
                'weight' => 'sometimes|numeric|min:0',
                'dimensions.length' => 'sometimes|numeric|min:0',
                'dimensions.width' => 'sometimes|numeric|min:0',
                'dimensions.height' => 'sometimes|numeric|min:0',
            ]);

            if ($validator->fails()) {
                Log::warning('Validación fallida al crear variación', [
                    'errors' => $validator->errors(),
                    'data_enviada' => $request->all(),
                    'storeId' => $storeId,
                    'productId' => $productId
                ]);
                return response()->json([
                    'message' => 'Error de validación.',
                    'errors' => $validator->errors(),
                    'status' => 'error'
                ], 422);
            }

            $data = $validator->validated();

            $variation = $woocommerce->post("products/{$productId}/variations", $data);

            return response()->json([
                'message' => 'Variación creada correctamente.',
                'variation' => $variation,
                'status' => 'success'
            ]);
        } catch (\Automattic\WooCommerce\HttpClient\HttpClientException $e) {
            Log::error('Error al crear la variación', [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'response_body' => method_exists($e, 'getResponse') ? $e->getResponse() : null,
                'woo_body' => method_exists($e, 'getResponse') ? $e->getResponse() : null,
                'data_enviada' => $request->all(),
                'storeId' => $storeId,
                'productId' => $productId,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Error al crear la variación.',
                'error' => $e->getMessage(),
                'status' => 'error'
            ], 500);
        } catch (\Exception $e) {
            Log::error('Error general al crear variación', [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'data_enviada' => $request->all(),
                'storeId' => $storeId,
                'productId' => $productId,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Error al crear la variación.',
                'error' => $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }

    public function deleteVariation($storeId, $productId, $variationId)
    {
        try {
            $woocommerce = $this->connect($storeId);

            $deleted = $woocommerce->delete("products/{$productId}/variations/{$variationId}", ['force' => true]);

            return response()->json([
                'message' => 'Variación eliminada correctamente.',
                'deleted_variation' => $deleted,
                'status' => 'success'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar la variación.',
                'error' => $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }

    /**
     * Validaciones específicas según el tipo de producto
     */
    private function validateProductType($productData)
    {
        $errors = [];

        switch ($productData['type']) {
            case 'external':
                if (empty($productData['external_url'])) {
                    $errors['external_url'] = ['La URL externa es requerida para productos externos.'];
                }
                break;

            case 'variable':
                if (empty($productData['attributes'])) {
                    $errors['attributes'] = ['Los atributos son requeridos para productos variables.'];
                }
                break;
        }

        // Validar que sale_price no sea mayor que regular_price
        if (!empty($productData['sale_price']) && !empty($productData['regular_price'])) {
            if ($productData['sale_price'] > $productData['regular_price']) {
                $errors['sale_price'] = ['El precio de oferta no puede ser mayor al precio regular.'];
            }
        }

        return $errors;
    }

    /**
     * Filtrar la respuesta del producto para mostrar solo campos relevantes
     */
    private function filterProductResponse($product, $woocommerce)
    {
        if ($product->type === 'variable') {
            $minPrice = null;
            foreach ($product->variations as $variationId) {
                $variation = $woocommerce->get("products/{$product->id}/variations/{$variationId}");
                $variationPrice = floatval($variation->sale_price ?: $variation->regular_price);
                if ($variationPrice > 0 && ($minPrice === null || $variationPrice < $minPrice)) {
                    $minPrice = $variationPrice;
                }
            }
            $product->price = $minPrice !== null ? (string)$minPrice : "";
        }

        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'type' => $product->type,
            'status' => $product->status,
            'featured' => $product->featured,
            'catalog_visibility' => $product->catalog_visibility,
            'description' => $product->description,
            'short_description' => $product->short_description,
            'sku' => $product->sku,
            'price' => $product->price,
            'regular_price' => $product->regular_price,
            'sale_price' => $product->sale_price,
            'price_html' => $product->price_html,
            'on_sale' => $product->on_sale,
            'purchasable' => $product->purchasable,
            'total_sales' => $product->total_sales,
            'virtual' => $product->virtual,
            'downloadable' => $product->downloadable,
            'external_url' => $product->external_url,
            'button_text' => $product->button_text,
            'tax_status' => $product->tax_status,
            'tax_class' => $product->tax_class,
            'manage_stock' => $product->manage_stock,
            'stock_quantity' => $product->stock_quantity,
            'stock_status' => $product->stock_status,
            'backorders' => $product->backorders,
            'backorders_allowed' => $product->backorders_allowed,
            'backordered' => $product->backordered,
            'weight' => $product->weight,
            'dimensions' => $product->dimensions,
            'shipping_required' => $product->shipping_required,
            'shipping_taxable' => $product->shipping_taxable,
            'shipping_class' => $product->shipping_class,
            'shipping_class_id' => $product->shipping_class_id,
            'reviews_allowed' => $product->reviews_allowed,
            'average_rating' => $product->average_rating,
            'rating_count' => $product->rating_count,
            'related_ids' => $product->related_ids,
            'upsell_ids' => $product->upsell_ids,
            'cross_sell_ids' => $product->cross_sell_ids,
            'parent_id' => $product->parent_id,
            'purchase_note' => $product->purchase_note,
            'categories' => $product->categories,
            'tags' => $product->tags,
            'images' => $product->images,
            'attributes' => $product->attributes,
            'variations' => $product->variations,
            'grouped_products' => $product->grouped_products,
            'menu_order' => $product->menu_order,
            'permalink' => $product->permalink,
            'date_created' => $product->date_created,
            'date_modified' => $product->date_modified,
        ];
    }

    /**
     * Crear un producto variable con sus variaciones
     */
    public function createVariableProduct(Request $request, $storeId)
    {
        Log::info('Iniciando creación de producto variable', [
            'storeId' => $storeId,
            'request_data' => $request->all(),
            'user_id' => optional(Auth::user())->id
        ]);

        try {
            $woocommerce = $this->connect($storeId);
            Log::info('Conexión a WooCommerce establecida correctamente', ['storeId' => $storeId]);

            // Validación de datos de entrada para producto variable
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'sometimes|string',
                'short_description' => 'sometimes|string',
                'sku' => 'sometimes|string|max:100',
                'status' => 'sometimes|in:draft,pending,private,publish',
                'featured' => 'sometimes|boolean',
                'catalog_visibility' => 'sometimes|in:visible,catalog,search,hidden',
                'categories' => 'sometimes|array',
                'categories.*.id' => 'required_with:categories|integer',
                'tags' => 'sometimes|array',
                'tags.*.id' => 'required_with:tags|integer',
                'images' => 'sometimes|array',
                'images.*.src' => 'required_with:images|url',
                'attributes' => 'required|array',
                'attributes.*.name' => 'required|string',
                'attributes.*.position' => 'sometimes|integer',
                'attributes.*.visible' => 'sometimes|boolean',
                'attributes.*.variation' => 'sometimes|boolean',
                'attributes.*.options' => 'required|array',
                'attributes.*.options.*' => 'required|string',
                'variations' => 'required|array',
                'variations.*.regular_price' => 'required|numeric|min:0',
                'variations.*.sale_price' => 'sometimes|numeric|min:0',
                'variations.*.attributes' => 'required|array',
                'variations.*.attributes.*.name' => 'required|string',
                'variations.*.attributes.*.option' => 'required|string',
                'variations.*.sku' => 'sometimes|string|max:100',
                'variations.*.stock_quantity' => 'sometimes|integer|min:0',
                'variations.*.manage_stock' => 'sometimes|boolean',
                'variations.*.stock_status' => 'sometimes|in:instock,outofstock,onbackorder',
                'variations.*.weight' => 'sometimes|numeric|min:0',
                'variations.*.dimensions.length' => 'sometimes|numeric|min:0',
                'variations.*.dimensions.width' => 'sometimes|numeric|min:0',
                'variations.*.dimensions.height' => 'sometimes|numeric|min:0',
                'variations.*.images' => 'sometimes|array',
                'variations.*.images.*.src' => 'required_with:variations.*.images|url',
            ]);

            if ($validator->fails()) {
                Log::warning('Validación fallida al crear producto variable', [
                    'errors' => $validator->errors(),
                    'request_data' => $request->all(),
                    'storeId' => $storeId
                ]);
                return response()->json([
                    'message' => 'Error de validación.',
                    'errors' => $validator->errors(),
                    'status' => 'error'
                ], 422);
            }

            $data = $validator->validated();
            $variations = $data['variations'];
            unset($data['variations']); // Remover variaciones del array principal

            // Campos por defecto para un producto variable
            $productData = array_merge([
                'type' => 'variable',
                'status' => 'publish',
                'featured' => false,
                'catalog_visibility' => 'visible',
                'manage_stock' => false,
                'stock_status' => 'instock',
                'virtual' => false,
                'downloadable' => false,
                'reviews_allowed' => true,
                'tax_status' => 'taxable',
            ], $data);

            // Configurar atributos para variaciones
            foreach ($productData['attributes'] as &$attribute) {
                $attribute['variation'] = true; // Habilitar variaciones para todos los atributos
                $attribute['visible'] = true;
            }

            Log::info('Enviando datos a WooCommerce para crear producto variable', [
                'productData' => $productData,
                'variations_count' => count($variations),
                'storeId' => $storeId
            ]);

            // Crear el producto variable en WooCommerce
            $created = $woocommerce->post("products", $productData);

            Log::info('Producto variable creado exitosamente', [
                'product_id' => $created->id,
                'storeId' => $storeId
            ]);

            // Crear las variaciones
            $createdVariations = [];
            foreach ($variations as $variationData) {
                try {
                    Log::info('Creando variación', [
                        'product_id' => $created->id,
                        'variation_data' => $variationData,
                        'storeId' => $storeId
                    ]);

                    $variation = $woocommerce->post("products/{$created->id}/variations", $variationData);
                    $createdVariations[] = $variation;

                    Log::info('Variación creada exitosamente', [
                        'variation_id' => $variation->id,
                        'product_id' => $created->id,
                        'storeId' => $storeId
                    ]);
                } catch (\Exception $e) {
                    Log::error('Error al crear variación', [
                        'message' => $e->getMessage(),
                        'variation_data' => $variationData,
                        'product_id' => $created->id,
                        'storeId' => $storeId
                    ]);
                    // Continuar con las siguientes variaciones
                }
            }

            // Obtener el producto completo con todas las variaciones
            $completeProduct = $woocommerce->get("products/{$created->id}");
            $filtered = $this->filterProductResponse($completeProduct, $woocommerce);

            Log::info('Producto variable completado', [
                'product_id' => $created->id,
                'variations_created' => count($createdVariations),
                'storeId' => $storeId
            ]);

            return response()->json([
                'message' => 'Producto variable creado correctamente.',
                'created_product' => $filtered,
                'variations_created' => count($createdVariations),
                'status' => 'success'
            ], 201);
        } catch (\Automattic\WooCommerce\HttpClient\HttpClientException $e) {
            Log::error('Error de WooCommerce API al crear producto variable', [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'storeId' => $storeId,
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Error de WooCommerce API.',
                'error' => $e->getMessage(),
                'status' => 'error'
            ], 400);
        } catch (\Exception $e) {
            Log::error('Error general al crear producto variable', [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'storeId' => $storeId,
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Error al crear el producto variable.',
                'error' => $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }


    /**
     * Asignar un producto de WooCommerce a una bodega
     */
    public function assignProductToWarehouse(Request $request, $storeId, $productId)
    {
        Log::info('Iniciando asignación de producto a bodega', [
            'storeId' => $storeId,
            'productId' => $productId,
            'request_data' => $request->all(),
            'user_id' => optional(Auth::user())->id
        ]);

        try {
            $woocommerce = $this->connect($storeId);

            // Validar datos de entrada
            $validator = Validator::make($request->all(), [
                'warehouse_id' => 'required|integer|exists:warehouses,id',
                'available_quantity' => 'required|integer|min:0',
                'price' => 'required|numeric|min:0',
                'condicion' => 'required|string|max:255',
                'currency_id' => 'required|string|max:255',
                'listing_type_id' => 'required|string|max:255',
                'category_id' => 'nullable|string|max:255',
                'attribute' => 'nullable|array',
                'pictures' => 'nullable|array',
                'sale_terms' => 'nullable|array',
                'shipping' => 'nullable|array',
                'description' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                Log::warning('Validación fallida al asignar producto a bodega', [
                    'errors' => $validator->errors(),
                    'request_data' => $request->all(),
                    'storeId' => $storeId,
                    'productId' => $productId
                ]);
                return response()->json([
                    'message' => 'Error de validación.',
                    'errors' => $validator->errors(),
                    'status' => 'error'
                ], 422);
            }

            // Obtener el producto de WooCommerce
            $wooProduct = $woocommerce->get("products/{$productId}");

            if (!$wooProduct) {
                return response()->json([
                    'message' => 'Producto de WooCommerce no encontrado.',
                    'status' => 'error'
                ], 404);
            }

            $data = $validator->validated();

            // Crear registro en stock_warehouses
            $stockWarehouse = \App\Models\StockWarehouse::create([
                'id_mlc' => $wooProduct->id, // Usar el ID de WooCommerce como id_mlc
                'warehouse_id' => $data['warehouse_id'],
                'title' => $wooProduct->name,
                'price' => $data['price'],
                'condicion' => $data['condicion'],
                'currency_id' => $data['currency_id'],
                'listing_type_id' => $data['listing_type_id'],
                'available_quantity' => $data['available_quantity'],
                'category_id' => $data['category_id'] ?? null,
                'attribute' => isset($data['attribute']) ? json_encode($data['attribute']) : json_encode([]),
                'pictures' => isset($data['pictures']) ? json_encode($data['pictures']) : json_encode($wooProduct->images ?? []),
                'sale_terms' => isset($data['sale_terms']) ? json_encode($data['sale_terms']) : json_encode([]),
                'shipping' => isset($data['shipping']) ? json_encode($data['shipping']) : json_encode([]),
                'description' => $data['description'] ?? $wooProduct->description ?? '',
            ]);

            Log::info('Producto asignado exitosamente a bodega', [
                'stock_warehouse_id' => $stockWarehouse->id,
                'warehouse_id' => $data['warehouse_id'],
                'woo_product_id' => $productId,
                'storeId' => $storeId
            ]);

            return response()->json([
                'message' => 'Producto asignado correctamente a la bodega.',
                'stock_warehouse' => $stockWarehouse,
                'woo_product' => $this->filterProductResponse($wooProduct, $woocommerce),
                'status' => 'success'
            ], 201);
        } catch (\Automattic\WooCommerce\HttpClient\HttpClientException $e) {
            Log::error('Error de WooCommerce API al asignar producto a bodega', [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'storeId' => $storeId,
                'productId' => $productId,
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Error de WooCommerce API.',
                'error' => $e->getMessage(),
                'status' => 'error'
            ], 400);
        } catch (\Exception $e) {
            Log::error('Error general al asignar producto a bodega', [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'storeId' => $storeId,
                'productId' => $productId,
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Error al asignar el producto a la bodega.',
                'error' => $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }

    /**
     * Crear producto en WooCommerce y asignarlo a una bodega en una sola operación
     */
    public function createProductAndAssignToWarehouse(Request $request, $storeId)
    {
        Log::info('Iniciando creación de producto y asignación a bodega', [
            'storeId' => $storeId,
            'request_data' => $request->all(),
            'user_id' => optional(Auth::user())->id
        ]);

        try {
            $woocommerce = $this->connect($storeId);

            // Validación de datos de entrada
            $validator = Validator::make($request->all(), [
                // Datos del producto WooCommerce
                'name' => 'required|string|max:255',
                'type' => 'sometimes|in:simple,grouped,external,variable',
                'regular_price' => 'sometimes|numeric|min:0',
                'sale_price' => 'sometimes|numeric|min:0',
                'description' => 'sometimes|string',
                'short_description' => 'sometimes|string',
                'sku' => 'sometimes|string|max:100',
                'manage_stock' => 'sometimes|boolean',
                'stock_quantity' => 'sometimes|integer|min:0',
                'stock_status' => 'sometimes|in:instock,outofstock,onbackorder',
                'weight' => 'sometimes|numeric|min:0',
                'dimensions.length' => 'sometimes|numeric|min:0',
                'dimensions.width' => 'sometimes|numeric|min:0',
                'dimensions.height' => 'sometimes|numeric|min:0',
                'categories' => 'sometimes|array',
                'categories.*.id' => 'required_with:categories|integer',
                'tags' => 'sometimes|array',
                'tags.*.id' => 'required_with:tags|integer',
                'images' => 'sometimes|array',
                'images.*.src' => 'required_with:images|url',
                'attributes' => 'sometimes|array',
                'status' => 'sometimes|in:draft,pending,private,publish',
                'featured' => 'sometimes|boolean',
                'catalog_visibility' => 'sometimes|in:visible,catalog,search,hidden',
                'virtual' => 'sometimes|boolean',
                'downloadable' => 'sometimes|boolean',
                'external_url' => 'sometimes|url',
                'button_text' => 'sometimes|string|max:255',
                'tax_status' => 'sometimes|in:taxable,shipping,none',
                'tax_class' => 'sometimes|string',
                'reviews_allowed' => 'sometimes|boolean',
                'purchase_note' => 'sometimes|string',
                'menu_order' => 'sometimes|integer',

                // Datos de asignación a bodega
                'warehouse_id' => 'required|integer|exists:warehouses,id',
                'warehouse_quantity' => 'required|integer|min:0',
                'warehouse_price' => 'required|numeric|min:0',
                'condicion' => 'required|string|max:255',
                'currency_id' => 'required|string|max:255',
                'listing_type_id' => 'required|string|max:255',
                'category_id' => 'nullable|string|max:255',
                'warehouse_attribute' => 'nullable|array',
                'warehouse_pictures' => 'nullable|array',
                'warehouse_sale_terms' => 'nullable|array',
                'warehouse_shipping' => 'nullable|array',
                'warehouse_description' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                Log::warning('Validación fallida al crear producto y asignar a bodega', [
                    'errors' => $validator->errors(),
                    'request_data' => $request->all(),
                    'storeId' => $storeId
                ]);
                return response()->json([
                    'message' => 'Error de validación.',
                    'errors' => $validator->errors(),
                    'status' => 'error'
                ], 422);
            }

            $data = $validator->validated();

            // Separar datos del producto WooCommerce
            $wooProductData = array_intersect_key($data, array_flip([
                'name',
                'type',
                'regular_price',
                'sale_price',
                'description',
                'short_description',
                'sku',
                'manage_stock',
                'stock_quantity',
                'stock_status',
                'weight',
                'dimensions',
                'categories',
                'tags',
                'images',
                'attributes',
                'status',
                'featured',
                'catalog_visibility',
                'virtual',
                'downloadable',
                'external_url',
                'button_text',
                'tax_status',
                'tax_class',
                'reviews_allowed',
                'purchase_note',
                'menu_order'
            ]));

            // Campos por defecto para un nuevo producto
            $productData = array_merge([
                'type' => 'simple',
                'status' => 'publish',
                'featured' => false,
                'catalog_visibility' => 'visible',
                'manage_stock' => false,
                'stock_status' => 'instock',
                'virtual' => false,
                'downloadable' => false,
                'reviews_allowed' => true,
                'tax_status' => 'taxable',
            ], $wooProductData);

            // Crear el producto en WooCommerce
            Log::info('Creando producto en WooCommerce', [
                'productData' => $productData,
                'storeId' => $storeId
            ]);

            $created = $woocommerce->post("products", $productData);

            Log::info('Producto creado exitosamente en WooCommerce', [
                'created_product' => $created,
                'storeId' => $storeId
            ]);

            // Crear registro en stock_warehouses
            $stockWarehouse = \App\Models\StockWarehouse::create([
                'id_mlc' => $created->id, // Usar el ID de WooCommerce como id_mlc
                'warehouse_id' => $data['warehouse_id'],
                'title' => $created->name,
                'price' => $data['warehouse_price'],
                'condicion' => $data['condicion'],
                'currency_id' => $data['currency_id'],
                'listing_type_id' => $data['listing_type_id'],
                'available_quantity' => $data['warehouse_quantity'],
                'category_id' => $data['category_id'] ?? null,
                'attribute' => isset($data['warehouse_attribute']) ? json_encode($data['warehouse_attribute']) : json_encode([]),
                'pictures' => isset($data['warehouse_pictures']) ? json_encode($data['warehouse_pictures']) : json_encode($created->images ?? []),
                'sale_terms' => isset($data['warehouse_sale_terms']) ? json_encode($data['warehouse_sale_terms']) : json_encode([]),
                'shipping' => isset($data['warehouse_shipping']) ? json_encode($data['warehouse_shipping']) : json_encode([]),
                'description' => $data['warehouse_description'] ?? $created->description ?? '',
            ]);

            Log::info('Producto asignado exitosamente a bodega', [
                'stock_warehouse_id' => $stockWarehouse->id,
                'warehouse_id' => $data['warehouse_id'],
                'woo_product_id' => $created->id,
                'storeId' => $storeId
            ]);

            // Respuesta filtrada con información relevante
            $filtered = $this->filterProductResponse($created, $woocommerce);

            return response()->json([
                'message' => 'Producto creado y asignado correctamente a la bodega.',
                'created_product' => $filtered,
                'stock_warehouse' => $stockWarehouse,
                'status' => 'success'
            ], 201);
        } catch (\Automattic\WooCommerce\HttpClient\HttpClientException $e) {
            Log::error('Error de WooCommerce API al crear producto y asignar a bodega', [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'storeId' => $storeId,
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Error de WooCommerce API.',
                'error' => $e->getMessage(),
                'status' => 'error'
            ], 400);
        } catch (\Exception $e) {
            Log::error('Error general al crear producto y asignar a bodega', [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'storeId' => $storeId,
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Error al crear el producto y asignarlo a la bodega.',
                'error' => $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }

    /**
     * Obtener productos de WooCommerce asignados a una bodega específica
     */
    public function getProductsByWarehouse($storeId, $warehouseId)
    {
        Log::info('Obteniendo productos de WooCommerce por bodega', [
            'storeId' => $storeId,
            'warehouseId' => $warehouseId,
            'user_id' => optional(Auth::user())->id
        ]);

        try {
            // Verificar que la bodega existe
            $warehouse = \App\Models\Warehouse::findOrFail($warehouseId);

            // Obtener productos de la bodega
            $stockWarehouses = \App\Models\StockWarehouse::where('warehouse_id', $warehouseId)->get();

            $woocommerce = $this->connect($storeId);
            $products = [];

            foreach ($stockWarehouses as $stockWarehouse) {
                try {
                    // Obtener producto de WooCommerce usando el id_mlc
                    $wooProduct = $woocommerce->get("products/{$stockWarehouse->id_mlc}");
                    $filteredProduct = $this->filterProductResponse($wooProduct, $woocommerce);

                    // Combinar datos del producto WooCommerce con datos de stock
                    $products[] = array_merge($filteredProduct, [
                        'stock_warehouse_id' => $stockWarehouse->id,
                        'warehouse_quantity' => $stockWarehouse->available_quantity,
                        'warehouse_price' => $stockWarehouse->price,
                        'warehouse_condicion' => $stockWarehouse->condicion,
                        'warehouse_currency_id' => $stockWarehouse->currency_id,
                        'warehouse_listing_type_id' => $stockWarehouse->listing_type_id,
                    ]);
                } catch (\Exception $e) {
                    Log::warning('Error al obtener producto de WooCommerce', [
                        'id_mlc' => $stockWarehouse->id_mlc,
                        'error' => $e->getMessage()
                    ]);
                    // Continuar con el siguiente producto
                }
            }

            Log::info('Productos obtenidos exitosamente', [
                'warehouseId' => $warehouseId,
                'products_count' => count($products),
                'storeId' => $storeId
            ]);

            return response()->json([
                'warehouse' => $warehouse,
                'products' => $products,
                'total_count' => count($products),
                'status' => 'success'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Bodega no encontrada', [
                'warehouseId' => $warehouseId,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'message' => 'Bodega no encontrada.',
                'error' => $e->getMessage(),
                'status' => 'error'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error al obtener productos por bodega', [
                'message' => $e->getMessage(),
                'warehouseId' => $warehouseId,
                'storeId' => $storeId,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Error al obtener productos por bodega.',
                'error' => $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }
}
