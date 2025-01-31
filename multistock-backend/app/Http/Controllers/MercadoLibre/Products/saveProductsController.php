<?php

namespace App\Http\Controllers\MercadoLibre\Products;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class saveProductsController
{

/**
     * Save products from API to database
     */
    public function saveProducts($clientId)
    {
        try {
            $savedCount = $this->mercadoLibreQueries->saveProductsFromApi($clientId);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Productos guardados con éxito',
                'data' => [
                    'saved_products' => $savedCount
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al guardar los productos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}