<?php

namespace App\Http\Controllers\Woocommerce;

use App\Models\WooStore;
use Automattic\WooCommerce\Client;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

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
            $filtered = $this->filterProductResponse($created);

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

            $filtered = $this->filterProductResponse($updated);

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
                'product' => $this->filterProductResponse($product),
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
            $params = array_filter($params, function($value) {
                return $value !== null && $value !== '';
            });

            $products = $woocommerce->get('products', $params);

            $filtered = array_map([$this, 'filterProductResponse'], $products);

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
                'deleted_product' => $this->filterProductResponse($deleted),
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
    private function filterProductResponse($product)
    {
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
}
