<?php

namespace App\Http\Controllers\MercadoLibre\Reportes;

use GuzzleHttp\Client;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class reviewController extends Controller
{
    private $client;

    public function __construct()
    {
        $this->client = new Client();
    }

    // Función para obtener opiniones usando el token de CRAZYFAMILY
    public function getReviewsCrazyFamily($productId)
    {
        $token = 'APP_USR-2999003706392728-022010-0da0b38f5e9638634c6aee1726b5efee-1750180786';
        return $this->getReviewsFromApi($productId, $token);
    }

    // Función para obtener opiniones usando el token de OFERTASIMPERDIBLESCHILE
    public function getReviewsOfertasImperdiblesChile($productId)
    {
        $token = 'APP_USR-83121941762985-022108-025ae93d8286c08caa72dec01a869cb5-1720493754';
        return $this->getReviewsFromApi($productId, $token);
    }

    // Función para obtener opiniones usando el token de LENCERIAONLINE
    public function getReviewsLenceriaOnline($productId)
    {
        $token = 'APP_USR-7365610229928727-021915-6818a73c46c27f927ddbcbe9d9bcb8e3-304267223';
        return $this->getReviewsFromApi($productId, $token);
    }

    // Función para obtener opiniones usando el token de COMERCIALIZADORAABIZICL
    public function getReviewsComercializadoraAbiziCl($productId)
    {
        $token = 'APP_USR-5822095179207900-022109-bc1711e2b00c06f2d5ffad0be732267d-1412503191';
        return $this->getReviewsFromApi($productId, $token);
    }

    // Función genérica para obtener opiniones desde la API
    private function getReviewsFromApi($productId, $token)
    {
        try {
            // Realizar la solicitud GET a la API de Mercado Libre
            $response = $this->client->request('GET', "https://api.mercadolibre.com/reviews/item/{$productId}", [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                ]
            ]);

            // Si la respuesta es exitosa, devolver los datos en formato JSON
            return response()->json(json_decode($response->getBody()->getContents(), true));
        } catch (\Exception $e) {
            // Si hay un error en la solicitud, devolver un mensaje de error
            return response()->json(['error' => 'No se pudieron obtener las reseñas', 'message' => $e->getMessage()], 400);
        }
    }
}
