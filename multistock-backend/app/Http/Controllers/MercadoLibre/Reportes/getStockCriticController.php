<?php

namespace App\Http\Controllers\MercadoLibre\Reportes;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Queue\SerializesModels;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
// use Spatie\Async\Pool;
// use GuzzleHttp\Client;
// use GuzzleHttp\Promise;

class getStockCriticController
{


    public function getStockCritic(Request $request, $clientId)
    {
        /*$datosDePrueba = [
            'productos' => [
                [
                    'id' => 'MLC12345678',
                    'title' => 'Smartphone XYZ Pro 128GB',
                    'available_quantity' => 2,
                    'price' => 899.99,
                    'permalink' => 'https://www.mercadolibre.com.mx/mlc-12345678'
                ],
                [
                    'id' => 'MLC87654321',
                    'title' => 'Laptop Ultradelgada 14" Core i7',
                    'available_quantity' => 1,
                    'price' => 1299.50,
                    'permalink' => 'https://www.mercadolibre.com.mx/mlc-87654321'
                ],
                [
                    'id' => 'MLC13579246',
                    'title' => 'Auriculares Inalámbricos NoiseCancel Pro',
                    'available_quantity' => 5,
                    'price' => 199.00,
                    'permalink' => 'https://www.mercadolibre.com.mx/mlc-13579246'
                ],
                [
                    'id' => 'MLC24680135',
                    'title' => 'Smartwatch Fitness Pro Edición Especial',
                    'available_quantity' => 0,
                    'price' => 249.99,
                    'permalink' => null
                ],
                [
                    'id' => 'MLC98765432',
                    'title' => 'Cámara DSLR 24MP con Lente 18-55mm',
                    'available_quantity' => 3,
                    'price' => 599.00,
                    'permalink' => 'https://www.mercadolibre.com.mx/mlc-98765432'
                ],
                [
                    'id' => 'MLC55555555',
                    'title' => 'Teclado Mecánico RGB Gaming',
                    'available_quantity' => 4,
                    'price' => 89.90,
                    'permalink' => 'https://www.mercadolibre.com.mx/mlc-55555555'
                ]
            ],
            'total_items_processed' => 150,
            'products_count' => 6
        ];*/
        set_time_limit(180);
        try {
            $validatedData = $request->validate([
                'excel' => 'sometimes|max:4',
                'mail' => 'sometimes|email|max:255',
            ]);
            if (isset($validatedData['excel'])) {
                $excel = $request->boolean('excel');
                error_log("excel " . json_encode($excel));
            }
            if (isset($validatedData['mail'])) {
                $mail = $validatedData['mail'];
            }
        } catch (\Exception $e) {
            // Log the error message
            error_log('Error en getStockCriticController: ' . $e->getMessage());
            // You can also log the stack trace for debugging purposes
            error_log('Stack Trace: ' . $e->getTraceAsString());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al procesar la solicitud.' . $e->getMessage(),
            ], 500);
        }



        if (empty($clientId) || !is_numeric($clientId)) {
            return response()->json([
                'status' => 'error',
                'message' => 'El clientId debe ser un número válido.',
            ], 400);
        }


        $cacheKey = "mercado_libre:stock_critic:{$clientId}";


        if (Cache::has($cacheKey)) {
            error_log("entro a cache download");
            $cachedData = Cache::get($cacheKey);

            if ($excel == true) {
                return $this->reportStockCriticExcel($cachedData);
            } else if ($mail) {
                return $this->reportStockCriticMail($cachedData, $mail);
            } else {
                // Ni excel ni mail fueron solicitados, devolver los datos en JSON
                return response()->json([
                    'status' => 'success',
                    'message' => 'Datos obtenidos de cache',
                    'data' => $cachedData,
                    'from_cache' => true
                ]);
            }
        }
        //Validar y obtener credenciales
        $credentials = MercadoLibreCredential::where('client_id', $clientId)->first();

        if (!$credentials) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se encontraron credenciales válidas para el client_id proporcionado.',
            ], 404);
        }
        try {
            if ($credentials->isTokenExpired()) {
                $refreshResponse = Http::asForm()->post('https://api.mercadolibre.com/oauth/token', [
                    'grant_type' => 'refresh_token',
                    'client_id' => $credentials->client_id,
                    'client_secret' => $credentials->client_secret,
                    'refresh_token' => $credentials->refresh_token,
                ]);
                // Si la solicitud falla, devolver un mensaje de error
                if ($refreshResponse->failed()) {
                    return response()->json(['error' => 'No se pudo refrescar el token'], 401);
                }

                $data = $refreshResponse->json();
                $credentials->update([
                    'access_token' => $data['access_token'],
                    'refresh_token' => $data['refresh_token'],
                    'expires_at' => now()->addSeconds($data['expires_in']),
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al refrescar token: ' . $e->getMessage(),
            ], 500);
        }
        // Comprobar si el token ha expirado y refrescarlo si es necesario


        $userResponse = Http::withToken($credentials->access_token)
            ->get('https://api.mercadolibre.com/users/me');

        if ($userResponse->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo obtener el ID del usuario.',
                'error' => $userResponse->json(),
            ], 500);
        }
        $userId = $userResponse->json()['id'];
        $limit = 100;
        $offset = 0;
        $totalItems = 0;
        $productsStock = [];
        $processedIds = [];

        // Construir la URL base
        $baseUrl = "https://api.mercadolibre.com/users/{$userId}/items/search";
        try {

            $maxProductos = 1000; // Ajustar según necesidades (1000 es el maximo)
            $productosProcessed = 0; //contador de productos para terminar la ejecucion el caso de alcanzar $maxProductos
            // $client = new Client([
            //     'timeout' => 30,
            //     'headers' => [
            //         'Authorization' => 'Bearer ' . $credentials->access_token
            //     ]
            // ]);
            do {
                // se arma la url para obtener lotes de IDs de productos para consultar a travez de ids
                $searchUrl = $baseUrl . (strpos($baseUrl, '?') !== false ? '&' : '?') .
                    http_build_query(['limit' => $limit, 'offset' => $offset]);
                error_log("URL: {$searchUrl}");
                $response = Http::timeout(30)->withToken($credentials->access_token)->get($searchUrl);
                if ($response->failed()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Error al conectar con la API de MercadoLibre.',
                        'error' => $response->json(),
                        'request_url' => $searchUrl,
                    ], $response->status());
                }
                $json = $response->json();
                $items = $json['results'] ?? [];
                $total = $json['paging']['total'] ?? 0;

                if (empty($items)) {
                    break; // No hay más items para procesar
                }

                //se separan los 100 items de la peticion en grupos de 20 que es el maximo
                // de items que se pueden pedir a la vez
                $itemBatches = array_chunk($items, 20);
                $totalItems += count($items);
                $productosProcessed += count($items);
                error_log("batches: " . json_encode($itemBatches));
                error_log("batches: " . json_encode($itemBatches));
                foreach ($itemBatches as $batch) {
                    //se hace una diferencia de los grupos de 20 con los ids ya procesadospara evitar duplicados

                    $uniqueBatch = array_diff($batch, $processedIds);
                    //se agergan los nuevos arrays
                    $processedIds = array_merge($processedIds, $uniqueBatch);
                    //en caso de que no haya nada que agregar se compienza denuevo el ciclo con el proximo lote de 20
                    if (empty($uniqueBatch)) continue;
                    //se formatea la id para la url asi consultar los ids de los productos
                    $batchIds = implode(',', $uniqueBatch);

                    //se hace la peticion a la api para obtener los datos de todos los productos del lote
                    //con este formato es mas rapido
                    $batchResponse = Http::withToken($credentials->access_token)
                        ->get("https://api.mercadolibre.com/items", [
                            'ids' => $batchIds,
                            'attributes' => 'id,title,available_quantity,price,permalink'
                        ]);
                    //se valida la respuesta de la peticion
                    if ($batchResponse->successful()) {
                        $batchResults = $batchResponse->json();

                        //se recorre el array de resultados se confirman la existencia de los datos y se agregan los productos con bajo stock
                        foreach ($batchResults as $itemResult) {
                            if (
                                $itemResult['code'] == 200 &&
                                isset($itemResult['body']['available_quantity']) &&
                                $itemResult['body']['available_quantity'] <= 5
                            ) {

                                $productsStock[] = [
                                    'id' => $itemResult['body']['id'],
                                    'title' => $itemResult['body']['title'],
                                    'available_quantity' => $itemResult['body']['available_quantity'],
                                    'price' => $itemResult['body']['price'] ?? null,
                                    'permalink' => $itemResult['body']['permalink'] ?? null
                                ];
                            }
                        }
                    }
                }
                //Solucion con asyncrona con peticiones
                 //paralelas aun no hago las pruebas por eso lo dejo comentado
                // foreach ($itemBatches as $batch) {
                //     // Filtrar IDs ya procesados
                //     $uniqueBatch = array_diff($batch, $processedIds);
                //     $processedIds = array_merge($processedIds, $uniqueBatch);

                //     if (empty($uniqueBatch)) continue;

                //     // Crear promesas para peticiones paralelas, esto permite enviar las siguientes peticiones
                //     // de forma paralela y obtener los resultados de forma sincrona.
                //     $promises = [];
                //     foreach (array_chunk($uniqueBatch, 20) as $subBatch) {
                //         $batchIds = implode(',', $subBatch);
                //         $promises[] = $client->getAsync('https://api.mercadolibre.com/items', [
                //             'query' => [
                //                 'ids' => $batchIds,
                //                 'attributes' => 'id,title,available_quantity,price,permalink'
                //             ]
                //         ]);
                //     }

                //     // Ejecutar todas las promesas en paralelo
                //     $results = Promise\Utils::settle(Promise\Utils::all($promises))->wait();

                //     foreach ($results as $result) {
                //         if ($result['state'] === 'fulfilled') {
                //             $batchResults = json_decode($result['value']->getBody(), true);
                //             foreach ($batchResults as $itemResult) {
                //                 if (
                //                     $itemResult['code'] == 200 &&
                //                     isset($itemResult['body']['available_quantity']) &&
                //                     $itemResult['body']['available_quantity'] <= 5
                //                 ) {
                //                     $productsStock[] = [
                //                         'id' => $itemResult['body']['id'],
                //                         'title' => $itemResult['body']['title'],
                //                         'available_quantity' => $itemResult['body']['available_quantity'],
                //                         'price' => $itemResult['body']['price'] ?? null,
                //                         'permalink' => $itemResult['body']['permalink'] ?? null
                //                     ];
                //                 }
                //             }
                //         }
                //     }
                // }


                $offset += $limit;

                // terminar si se procesaron todos los productos
                if ($productosProcessed >= $maxProductos) {
                    break;
                }
            } while ($offset < $total);

            $responseData = [
                'total_items_processed' => $totalItems,
                'products_count' => count($productsStock),
                'productos' => $productsStock

            ];
            error_log("datos" . json_encode($responseData));

            Cache::put($cacheKey, $responseData, now()->addMinutes(10));
            if (isset($validatedData['excel']) && $excel == true) {
                return $this->reportStockCriticExcel($productsStock);
            } else if ($mail) {
                return $this->reportStockCriticMail($productsStock, $mail);
            } else {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Productos obtenidos correctamente',
                    'data' => $responseData,
                    'from_cache' => false
                ], 200);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al procesar datos: ' . $e->getMessage(),
            ], 500);
        }
    }
    private function reportStockCriticMail($result, $email = null)
    {
        try {
            if (!empty($result)) {
                $directory = 'public/reports';
                Storage::makeDirectory($directory); // Asegura que el directorio existe

                $fileName = 'reports/stock_critico_' . date('Ymd_His') . '.xlsx';
                $storagePath = $fileName;

                // Crear el archivo Excel con PhpSpreadsheet
                $spreadsheet = $this->createStockCriticoSpreadsheet($result);
                $writer = new Xlsx($spreadsheet);

                // Guardar el archivo en el storage
                $fullPath = storage_path('app/' . $storagePath);
                $writer->save($fullPath);

                // Verificar que el archivo existe
                if (!file_exists($fullPath)) {
                    throw new \Exception("No se pudo generar el archivo Excel");
                }

                // Enviar por correo electrónico con el archivo adjunto
                Mail::to($email)->send(new StockCriticoReport($storagePath));

                // Eliminar el archivo después de enviar el correo
                if (file_exists($fullPath)) {
                    unlink($fullPath);
                    error_log('Archivo Excel eliminado después de enviar el correo: ' . $storagePath);
                }

                return response()->json([
                    'status' => 'success',
                    'message' => 'Reporte generado y enviado por email',
                    'email' => $email,
                    'data_count' => is_array($result['productos'] ?? $result) ? count($result['productos'] ?? $result) : 'N/A'
                ]);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No hay datos para generar el reporte',
                ], 404);
            }
        } catch (\Exception $e) {
            error_log('Error al generar o enviar mail: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al procesar los datos y enviar mail: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function reportStockCriticExcel($result)
    {
        try {


            if (!empty($result)) {
                $fileName = 'stock_critico_' . date('Ymd_His') . '.xlsx';

                // Crear un archivo temporal para guardar el Excel
                $tempFile = tempnam(sys_get_temp_dir(), 'stock_critico_');

                // Crear el objeto Spreadsheet
                $spreadsheet = $this->createStockCriticoSpreadsheet($result);

                // Crear el writer y guardar en el archivo temporal
                $writer = new Xlsx($spreadsheet);
                $writer->save($tempFile);

                // Leer el contenido del archivo
                $fileContent = file_get_contents($tempFile);

                // Eliminar el archivo temporal
                @unlink($tempFile);

                // Crear la respuesta con el archivo como contenido
                return response($fileContent)
                    ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                    ->header('Content-Disposition', 'attachment; filename="' . $fileName . '"')
                    ->header('Content-Length', strlen($fileContent))
                    ->header('Cache-Control', 'max-age=0');
            } else {
                error_log('No hay datos para generar Excel');
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se encontraron datos para generar el reporte',
                    'received_keys' => empty($result) ? [] : array_keys($result)
                ], 404);
            }
        } catch (\Exception $e) {
            error_log('Error al generar el archivo Excel: ' . $e->getMessage());
            error_log('Traza: ' . $e->getTraceAsString());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al generar el archivo Excel: ' . $e->getMessage(),
            ], 500);
        }
    }


    private function createStockCriticoSpreadsheet($data)
    {
        // Asegurarse de que tenemos la estructura correcta de datos
        $productos = isset($data['productos']) ? $data['productos'] : $data;

        // Crear una nueva instancia de Spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Stock Crítico');

        // Definir los encabezados
        $sheet->setCellValue('A1', 'ID');
        $sheet->setCellValue('B1', 'Producto');
        $sheet->setCellValue('C1', 'Stock Actual');
        $sheet->setCellValue('D1', 'Precio');
        $sheet->setCellValue('E1', 'Enlace');

        // Dar formato a la fila de encabezados
        $headerStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF']
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN
                ]
            ]
        ];

        $headerRange = 'A1:E1';
        $sheet->getStyle($headerRange)->applyFromArray($headerStyle);

        // Agregar los datos de los productos
        $row = 2;
        foreach ($productos as $producto) {
            $sheet->setCellValue('A' . $row, $producto['id']);
            $sheet->setCellValue('B' . $row, $producto['title']);
            $sheet->setCellValue('C' . $row, $producto['available_quantity']);
            $sheet->setCellValue('D' . $row, $producto['price'] ?? 'N/A');

            // Crear un enlace clicable si existe permalink
            if (!empty($producto['permalink'])) {
                $sheet->setCellValue('E' . $row, $producto['permalink']);
                $sheet->getCell('E' . $row)->getHyperlink()->setUrl($producto['permalink']);
                $sheet->getStyle('E' . $row)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_BLUE));
                $sheet->getStyle('E' . $row)->getFont()->setUnderline(true);
            } else {
                $sheet->setCellValue('E' . $row, '');
            }

            // Aplicar estilo según cantidad de stock (rojo si es crítico)
            if ($producto['available_quantity'] <= 2) {
                $sheet->getStyle('C' . $row)->getFont()->getColor()->setRGB('FF0000');
                $sheet->getStyle('C' . $row)->getFont()->setBold(true);
            }

            $row++;
        }

        // Auto-dimensionar columnas
        foreach (range('A', 'E') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Congelar la primera fila
        $sheet->freezePane('A2');

        // Agregar filtros
        $lastRow = count($productos) + 1;
        $sheet->setAutoFilter('A1:E' . $lastRow);

        return $spreadsheet;
    }
}




class StockCriticoReport extends Mailable
{
    use Queueable, SerializesModels;

    public $fileName;

    public function __construct($fileName)
    {
        $this->fileName = $fileName;
    }

    public function build()
    {
        $filePath = storage_path('app/' . $this->fileName);

        if (!file_exists($filePath)) {
            Log::error("Archivo no encontrado: " . $filePath);
            throw new \Exception("El archivo adjunto no existe");
        }

        return $this->subject('Reporte de Stock Crítico - ' . date('d/m/Y'))
            ->view('emails.stock_report')
            ->attach($filePath, [
                'as' => 'stock_critico.xlsx',
                'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
    }
}
