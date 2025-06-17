<?php

namespace App\Http\Controllers\Woocommerce;

use App\Models\WooStore;
use Automattic\WooCommerce\Client;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class WooCategoryController extends Controller
{
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
    /**
     * Listar categorías de productos
     */
    public function listCategories($storeId, Request $request)
    {
        try {
            $woocommerce = $this->connect($storeId);

            // Validación de parámetros de entrada
            $validator = Validator::make($request->all(), [
                'per_page' => 'sometimes|integer|min:1|max:100',
                'page' => 'sometimes|integer|min:1',
                'search' => 'sometimes|string|max:255',
                'exclude' => 'sometimes|array',
                'exclude.*' => 'integer',
                'include' => 'sometimes|array',
                'include.*' => 'integer',
                'order' => 'sometimes|in:asc,desc',
                'orderby' => 'sometimes|in:id,include,name,slug,term_group,description,count',
                'hide_empty' => 'sometimes|boolean',
                'parent' => 'sometimes|integer',
                'product' => 'sometimes|integer',
                'slug' => 'sometimes|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Error de validación.',
                    'errors' => $validator->errors(),
                    'status' => 'error'
                ], 422);
            }

            // Parámetros de consulta con valores por defecto
            $params = [
                'per_page' => $request->get('per_page', 10),
                'page' => $request->get('page', 1),
                'search' => $request->get('search'),
                'exclude' => $request->get('exclude'),
                'include' => $request->get('include'),
                'order' => $request->get('order', 'asc'),
                'orderby' => $request->get('orderby', 'name'),
                'hide_empty' => $request->get('hide_empty'),
                'parent' => $request->get('parent'),
                'product' => $request->get('product'),
                'slug' => $request->get('slug'),
            ];

            // Filtrar parámetros vacíos
            $params = array_filter($params, function($value) {
                return $value !== null && $value !== '';
            });

            $categories = $woocommerce->get('products/categories', $params);

            $filtered = array_map([$this, 'filterCategoryResponse'], $categories);

            return response()->json([
                'categories' => $filtered,
                'total_categories' => count($filtered),
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
                'message' => 'Error al listar categorías.',
                'error' => $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }

    /**
     * Obtener una categoría específica
     */
    public function getCategory($storeId, $categoryId)
    {
        try {
            $woocommerce = $this->connect($storeId);

            $category = $woocommerce->get("products/categories/{$categoryId}");

            return response()->json([
                'category' => $this->filterCategoryResponse($category),
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
                'message' => 'Error al obtener la categoría.',
                'error' => $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }

    /**
     * Crear una nueva categoría
     */
    public function createCategory(Request $request, $storeId)
    {
        try {
            $woocommerce = $this->connect($storeId);

            // Validación de datos de entrada
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'slug' => 'sometimes|string|max:255',
                'parent' => 'sometimes|integer|min:0',
                'description' => 'sometimes|string',
                'display' => 'sometimes|in:default,products,subcategories,both',
                'image' => 'sometimes|array',
                'image.src' => 'required_with:image|url',
                'image.alt' => 'sometimes|string|max:255',
                'menu_order' => 'sometimes|integer|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Error de validación.',
                    'errors' => $validator->errors(),
                    'status' => 'error'
                ], 422);
            }

            $data = $validator->validated();

            // Campos por defecto para una nueva categoría
            $categoryData = array_merge([
                'display' => 'default',
                'menu_order' => 0,
            ], $data);

            // Crear la categoría en WooCommerce
            $created = $woocommerce->post("products/categories", $categoryData);

            return response()->json([
                'message' => 'Categoría creada correctamente.',
                'created_category' => $this->filterCategoryResponse($created),
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
                'message' => 'Error al crear la categoría.',
                'error' => $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }

    /**
     * Actualizar una categoría existente
     */
    public function updateCategory(Request $request, $storeId, $categoryId)
    {
        try {
            $woocommerce = $this->connect($storeId);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255',
                'slug' => 'sometimes|string|max:255',
                'parent' => 'sometimes|integer|min:0',
                'description' => 'sometimes|string',
                'display' => 'sometimes|in:default,products,subcategories,both',
                'image' => 'sometimes|array',
                'image.src' => 'required_with:image|url',
                'image.alt' => 'sometimes|string|max:255',
                'menu_order' => 'sometimes|integer|min:0',
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

            $updated = $woocommerce->put("products/categories/{$categoryId}", $data);

            return response()->json([
                'message' => 'Categoría actualizada correctamente.',
                'updated_category' => $this->filterCategoryResponse($updated),
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
                'message' => 'Error al actualizar la categoría.',
                'error' => $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }

    /**
     * Eliminar una categoría
     */
    public function deleteCategory($storeId, $categoryId, Request $request)
    {
        try {
            $woocommerce = $this->connect($storeId);

            $params = [
                'force' => $request->get('force', false)
            ];

            $deleted = $woocommerce->delete("products/categories/{$categoryId}", $params);

            return response()->json([
                'message' => 'Categoría eliminada correctamente.',
                'deleted_category' => $this->filterCategoryResponse($deleted),
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
                'message' => 'Error al eliminar la categoría.',
                'error' => $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }

    /**
     * Filtrar la respuesta de la categoría para mostrar solo campos relevantes
     */
    private function filterCategoryResponse($category)
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'parent' => $category->parent,
            'description' => $category->description,
            'display' => $category->display,
            'image' => $category->image,
            'menu_order' => $category->menu_order,
            'count' => $category->count,
            '_links' => isset($category->_links) ? $category->_links : null,
        ];
    }
}
