<?php
namespace App\Http\Controllers\MercadoLibre\Products;

use App\Models\SizeGrid;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;

class SizeGridController extends Controller
{

    public function store(Request $request)
    {
        // Validación de datos
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'value_name' => 'nullable|string|max:255',
            'sizes' => 'required|array|min:1',
            'sizes.*.name' => 'required|string|max:255',
            'sizes.*.value' => 'required|string|max:255',
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

            // Crear el SizeGrid
            $sizeGrid = SizeGrid::create([
                'name' => $request->name,
                'value_name' => $request->value_name,
            ]);

            // Crear las tallas asociadas
            $sizesData = [];
            foreach ($request->sizes as $size) {
                $sizesData[] = [
                    'name' => $size['name'],
                    'value' => $size['value'],
                    'size_grid_id' => $sizeGrid->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Insertar todas las tallas de una vez
            $sizeGrid->sizes()->insert($sizesData);

            // Si se solicita enviar a MercadoLibre
            if ($request->get('send_to_meli', false)) {
                $meliResponse = $this->createMeliSizeChart($sizeGrid, $request->sizes);

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

    private function createMeliSizeChart($sizeGrid, $sizes)
    {
        try {
            // Formatear los datos para MercadoLibre
            $rows = [];
            foreach ($sizes as $size) {
                $rows[] = [
                    $size['name'] => $size['value']
                ];
            }

            $meliData = [
                'name' => $sizeGrid->name,
                'rows' => $rows
            ];


            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' ,
                'Content-Type' => 'application/json'
            ])->post('https://api.mercadolibre.com/size_charts', $meliData);

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

    /**
     * Obtener todos los SizeGrids con sus tallas
     */
    public function index()
    {
        try {
            $sizeGrids = SizeGrid::with('sizes')->get();

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
    public function show($id)
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
     * Actualizar un SizeGrid y sus tallas
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'value_name' => 'nullable|string|max:255',
            'sizes' => 'required|array|min:1',
            'sizes.*.name' => 'required|string|max:255',
            'sizes.*.value' => 'required|string|max:255',
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

            $sizeGrid = SizeGrid::findOrFail($id);

            // Actualizar el SizeGrid
            $sizeGrid->update([
                'name' => $request->name,
                'value_name' => $request->value_name,
            ]);

            // Eliminar tallas existentes
            $sizeGrid->sizes()->delete();

            // Crear las nuevas tallas
            $sizesData = [];
            foreach ($request->sizes as $size) {
                $sizesData[] = [
                    'name' => $size['name'],
                    'value' => $size['value'],
                    'size_grid_id' => $sizeGrid->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            $sizeGrid->sizes()->insert($sizesData);

            DB::commit();

            $sizeGrid->load('sizes');

            return response()->json([
                'success' => true,
                'message' => 'SizeGrid actualizado exitosamente',
                'data' => $sizeGrid
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el SizeGrid',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar un SizeGrid y sus tallas
     */
    public function destroy($id)
    {
        try {
            $sizeGrid = SizeGrid::findOrFail($id);
            $sizeGrid->delete(); // Las tallas se eliminarán automáticamente por el cascade

            return response()->json([
                'success' => true,
                'message' => 'SizeGrid eliminado exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el SizeGrid',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
