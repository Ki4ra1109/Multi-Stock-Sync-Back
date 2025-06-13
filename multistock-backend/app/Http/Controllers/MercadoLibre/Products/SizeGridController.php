<?php

namespace App\Http\Controllers\MercadoLibre\Products;

use App\Http\Controllers\Controller;
use App\Models\MercadoLibreCredential;
use App\Models\SizeGrid;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

use Illuminate\Support\Facades\Http;

class SizeGridController extends Controller
{
    public function createSizeGrid(Request $request, $client_id)
    {
        $credentials = MercadoLibreCredential::where('client_id', $client_id)->first();

        if (!$credentials || $credentials->isTokenExpired()) {
            return response()->json(['status' => 'error', 'message' => 'Token no válido o expirado.'], 401);
        }

        // Validación de datos mejorada
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'domain_id' => 'required|string', // Dominio del producto (SNEAKERS, PANTS, etc.)
            'site_id' => 'required|string', // MLA, MLB, MLC, etc.
            'gender' => 'required|array|min:1', // Género requerido
            'gender.*.id' => 'nullable|string',
            'gender.*.name' => 'required|string',
            'main_attribute' => 'required|array',
            'main_attribute.attributes' => 'required|array|min:1',
            'main_attribute.attributes.*.site_id' => 'required|string',
            'main_attribute.attributes.*.id' => 'required|string',
            'measure_type' => 'nullable|in:BODY_MEASURE,CLOTHING_MEASURE',
            'rows' => 'required|array|min:1',
            'rows.*.attributes' => 'required|array|min:1',
            'rows.*.attributes.*.id' => 'required|string',
            'rows.*.attributes.*.values' => 'required|array|min:1',
            'rows.*.attributes.*.values.*.name' => 'required|string',
            'rows.*.attributes.*.values.*.id' => 'nullable|string', // Para valores de lista
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Crear el SizeGrid local con datos adicionales
            $sizeGrid = SizeGrid::create([
                'name' => $request->name,
                'domain_id' => $request->domain_id,
                'site_id' => $request->site_id,
                'measure_type' => $request->measure_type ?? 'BODY_MEASURE',
                'gender' => json_encode($request->gender),
                'main_attribute' => json_encode($request->main_attribute),
            ]);

            // Crear las filas de tallas localmente
            $this->createLocalSizeRows($sizeGrid, $request->rows);

            // Si se solicita enviar a MercadoLibre
            if ($request->get('send_to_meli', false)) {
                $meliResponse = $this->createMeliSizeChart($sizeGrid, $request->all(), $credentials);

                if ($meliResponse['success']) {
                    // Actualizar el SizeGrid con el ID de MercadoLibre
                    $sizeGrid->update(['meli_chart_id' => $meliResponse['chart_id']]);
                } else {
                    // Si falla ML, hacer rollback y devolver el error
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Error al crear la guía de tallas en MercadoLibre',
                        'meli_error' => $meliResponse['error']
                    ], 500);
                }
            }

            DB::commit();

            // Cargar las relaciones para la respuesta
            $sizeGrid->load('sizes');

            $response = [
                'success' => true,
                'message' => 'SizeGrid creado exitosamente',
                'data' => $sizeGrid
            ];

            // Agregar información de MercadoLibre si se procesó
            if ($request->get('send_to_meli', false)) {
                $response['mercadolibre'] = $meliResponse;
            }

            return response()->json($response, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el SizeGrid',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function createLocalSizeRows($sizeGrid, $rows)
    {
        $sizesData = [];
        foreach ($rows as $index => $row) {
            $sizesData[] = [
                'row_index' => $index + 1,
                'attributes' => json_encode($row['attributes']),
                'size_grid_id' => $sizeGrid->id,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Insertar todas las filas de una vez
        $sizeGrid->sizes()->insert($sizesData);
    }

    private function createMeliSizeChart($sizeGrid, $requestData, $credentials)
    {
        try {
            // Formatear los datos según la estructura de MercadoLibre
            $meliData = [
                'names' => [
                    $requestData['site_id'] => $requestData['name']
                ],
                'domain_id' => $requestData['domain_id'],
                'site_id' => $requestData['site_id'],
                'main_attribute' => $requestData['main_attribute'],
                'attributes' => [
                    [
                        'id' => 'GENDER',
                        'values' => $requestData['gender']
                    ]
                ],
                'rows' => $requestData['rows']
            ];

            // Agregar measure_type si está presente
            if (isset($requestData['measure_type'])) {
                $meliData['measure_type'] = $requestData['measure_type'];
            }

            $response = Http::withToken($credentials->access_token)->timeout(60)->post('https://api.mercadolibre.com/catalog/charts', $meliData);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'chart_id' => $response->json('id'),
                    'response' => $response->json()
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $response->json(),
                    'status_code' => $response->status()
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    // Agregar validación del dominio antes de crear
    private function validateDomainTechnicalSpecs($domain_id, $credentials)
    {
        $response = Http::withToken($credentials->access_token)
            ->get("https://api.mercadolibre.com/domains/{$domain_id}/technical_specs/size_chart", [
                'site_id' => 'MLC'
            ]);

        return $response->successful() ? $response->json() : null;
    }
    /**
     * Obtener todos los SizeGrids con sus tallas
     */
    public function listSizeGrids($client_id)
    {
        try {
            $query = SizeGrid::with('sizes');

            // Si se proporciona client_id, filtrar por ese usuario
            if ($client_id) {
                $query->where('client_id', $client_id);
            }

            $sizeGrids = $query->get();

            return response()->json([
                'success' => true,
                'data' => $sizeGrids
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los SizeGrids',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener un SizeGrid específico con sus tallas
     */
    public function showSizeGrid($id)
    {
        try {
            $sizeGrid = SizeGrid::with('sizes')->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $sizeGrid
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'SizeGrid no encontrado',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Agregar fila a una guía de tallas existente
     */
    public function addRowToSizeGrid(Request $request, $id, $client_id)
    {
        $credentials = MercadoLibreCredential::where('client_id', $client_id)->first();

        if (!$credentials || $credentials->isTokenExpired()) {
            return response()->json(['status' => 'error', 'message' => 'Token no válido o expirado.'], 401);
        }

        $validator = Validator::make($request->all(), [
            'attributes' => 'required|array|min:1',
            'attributes.*.id' => 'required|string',
            'attributes.*.values' => 'required|array|min:1',
            'attributes.*.values.*.name' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $sizeGrid = SizeGrid::findOrFail($id);

            DB::beginTransaction();

            // Agregar fila localmente
            $maxRowIndex = $sizeGrid->sizes()->max('row_index') ?? 0;
            $sizeGrid->sizes()->create([
                'row_index' => $maxRowIndex + 1,
                'attributes' => json_encode($request->attributes),
            ]);

            // Si tiene meli_chart_id, agregar también en MercadoLibre
            if ($sizeGrid->meli_chart_id && $request->get('send_to_meli', true)) {
                $meliResponse = $this->addRowToMeliChart($sizeGrid->meli_chart_id, $request->attributes, $credentials);

                if (!$meliResponse['success']) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Error al agregar fila en MercadoLibre',
                        'meli_error' => $meliResponse['error']
                    ], 500);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Fila agregada exitosamente',
                'data' => $sizeGrid->load('sizes')
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al agregar fila',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function addRowToMeliChart($chartId, $attributes, $credentials)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $credentials->access_token,
                'Content-Type' => 'application/json'
            ])->post("https://api.mercadolibre.com/catalog/charts/{$chartId}/rows", [
                'attributes' => $attributes
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'response' => $response->json()
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $response->json(),
                    'status_code' => $response->status()
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Consultar guía de tallas desde MercadoLibre
     */
    public function getMeliSizeChart($chartId, $client_id)
    {
        $credentials = MercadoLibreCredential::where('client_id', $client_id)->first();

        if (!$credentials || $credentials->isTokenExpired()) {
            return response()->json(['status' => 'error', 'message' => 'Token no válido o expirado.'], 401);
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $credentials->access_token,
                'Content-Type' => 'application/json'
            ])->get("https://api.mercadolibre.com/catalog/charts/{$chartId}");

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'data' => $response->json()
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al consultar guía de tallas en MercadoLibre',
                    'error' => $response->json()
                ], $response->status());
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al consultar guía de tallas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener dominios disponibles para guías de tallas
     */
    public function getAvailableDomains($client_id)
    {
        $credentials = MercadoLibreCredential::where('client_id', $client_id)->first();

        if (!$credentials || $credentials->isTokenExpired()) {
            return response()->json(['status' => 'error', 'message' => 'Token no válido o expirado.'], 401);
        }

        try {
            // Obtener dominios disponibles desde MercadoLibre
            $response = Http::withToken($credentials->access_token)->timeout(60)->get('https://api.mercadolibre.com/catalog/charts/MLC/configurations/active_domains');
            json_encode($response);
            if ($response->successful()) {

                return response()->json([
                    'success' => true,
                    'data' => $response->json()
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al obtener dominios',
                    'error' => $response->json()
                ], $response->status());
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener dominios',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getDomain($domain_id, $client_id)
    {
        $credentials = MercadoLibreCredential::where('client_id', $client_id)->first();

        if (!$credentials || $credentials->isTokenExpired()) {
            return response()->json(['status' => 'error', 'message' => 'Token no válido o expirado.'], 401);
        }

        try {
            // Endpoint correcto para obtener las especificaciones técnicas del dominio
            $response = Http::withToken($credentials->access_token)
                ->timeout(30)
                ->get("https://api.mercadolibre.com/domains/{$domain_id}/technical_specs");

            if ($response->successful()) {
                $data = $response->json();

                // Filtrar solo los atributos relevantes para size charts
                $sizeChartData = $this->filterSizeChartAttributes($data);

                return response()->json([
                    'success' => true,
                    'domain_id' => $domain_id,
                    'data' => $sizeChartData,
                    'full_data' => $data // Incluye todos los datos por si necesitas más información
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al obtener información del dominio',
                    'error' => $response->json(),
                    'status_code' => $response->status()
                ], $response->status());
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al consultar el dominio',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Filtra los atributos relevantes para size charts
     */
    private function filterSizeChartAttributes($data)
    {
        $sizeChartAttributes = [];
        $measureTypes = [];
        $sizeRelatedIds = ['SIZE', 'GENDER', 'AGE_GROUP', 'PANTY_TYPE', 'PANTY_RISE'];

        if (isset($data['input']['groups'])) {
            foreach ($data['input']['groups'] as $group) {
                if (isset($group['components'])) {
                    foreach ($group['components'] as $component) {
                        if (isset($component['attributes'])) {
                            foreach ($component['attributes'] as $attribute) {
                                $attributeId = $attribute['id'] ?? null;

                                // Verificar si es un atributo relevante para size charts
                                $isRelevant = false;

                                // Atributos principales de talla
                                if ($attributeId === 'SIZE') {
                                    $isRelevant = true;
                                }

                                // Atributos relacionados con medidas
                                if (isset($attribute['tags']) && is_array($attribute['tags'])) {
                                    if (in_array('CLOTHING_MEASURE', $attribute['tags']) ||
                                        in_array('BODY_MEASURE', $attribute['tags'])) {
                                        $isRelevant = true;
                                        $measureTypes[] = in_array('CLOTHING_MEASURE', $attribute['tags']) ?
                                            'CLOTHING_MEASURE' : 'BODY_MEASURE';
                                    }
                                }

                                // Atributos relacionados con tallas
                                if (in_array($attributeId, $sizeRelatedIds)) {
                                    $isRelevant = true;
                                }

                                if ($isRelevant) {
                                    $sizeChartAttributes[] = [
                                        'id' => $attributeId,
                                        'name' => $attribute['name'] ?? $attributeId,
                                        'value_type' => $attribute['value_type'] ?? 'string',
                                        'tags' => $attribute['tags'] ?? [],
                                        'values' => $attribute['values'] ?? null,
                                        'required' => in_array('required', $attribute['tags'] ?? []),
                                        'hierarchy' => $attribute['hierarchy'] ?? null,
                                        'component' => $component['component'] ?? null
                                    ];
                                }
                            }
                        }
                    }
                }
            }
        }

        return [
            'domain_id' => $data['domain_id'] ?? null,
            'size_chart_attributes' => $sizeChartAttributes,
            'measure_types_available' => array_unique($measureTypes)
        ];
    }
    /**
     * Determina qué tipos de medida están disponibles según los atributos
     */
    private function getMeasureTypesFromAttributes($attributes)
    {
        $measureTypes = [];

        foreach ($attributes as $attribute) {
            if (isset($attribute['tags']) && is_array($attribute['tags'])) {
                if (in_array('CLOTHING_MEASURE', $attribute['tags'])) {
                    $measureTypes[] = 'CLOTHING_MEASURE';
                }
                if (in_array('BODY_MEASURE', $attribute['tags'])) {
                    $measureTypes[] = 'BODY_MEASURE';
                }
            }
        }

        return array_unique($measureTypes);
    }
}
