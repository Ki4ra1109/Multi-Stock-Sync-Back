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

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request as GuzzleRequest;

class getStockCriticController
{
    private $batchSize = 100; // Aumentamos el tamaño del lote para reducir el número de llamadas
    private $maxConcurrent = 5; // Número máximo de solicitudes concurrentes

    public function getStockCritic(Request $request, $clientId)
    {
        set_time_limit(240); // 4 minutos máximo
        try {
            $validatedData = $request->validate([
                'excel' => 'sometimes|max:4',
                'mail' => 'sometimes|email|max:255',
            ]);
            $excel = false;
            $mail = null;
            if (isset($validatedData['excel'])) {
                $excel = $request->boolean('excel');
                Log::info("Excel output requested", ['excel' => $excel]);
            }
            if (isset($validatedData['mail'])) {
                $mail = $validatedData['mail'];
                Log::info("Mail output requested", ['mail' => $mail]);
            }
        } catch (\Exception $e) {
            Log::error('Error en validación de datos:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage(),
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
            Log::info("Returning cached data for client", ['client_id' => $clientId]);
            $cachedData = Cache::get($cacheKey);

            if ($excel == true) {
                return $this->reportStockCriticExcel($cachedData);
            } else if ($mail) {
                return $this->reportStockCriticMail($cachedData, $mail);
            } else {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Datos obtenidos de cache',
                    'data' => $cachedData,
                    'from_cache' => true
                ]);
            }
        }

        $credentials = MercadoLibreCredential::where('client_id', $clientId)->first();
        if (!$credentials) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se encontraron credenciales válidas para el client_id proporcionado.',
            ], 404);
        }

        try {
            if ($credentials->isTokenExpired()) {
                Log::info("Refreshing expired token for client", ['client_id' => $clientId]);
                $refreshResponse = Http::asForm()->post('https://api.mercadolibre.com/oauth/token', [
                    'grant_type' => 'refresh_token',
                    'client_id' => $credentials->client_id,
                    'client_secret' => $credentials->client_secret,
                    'refresh_token' => $credentials->refresh_token,
                ]);

                if ($refreshResponse->failed()) {
                    Log::error("Token refresh failed", [
                        'client_id' => $clientId,
                        'response' => $refreshResponse->json()
                    ]);
                    return response()->json([
                        'status' => 'error',
                        'message' => 'El token ha expirado y no se pudo refrescar',
                        'error' => $refreshResponse->json(),
                    ], 401);
                }

                $newTokenData = $refreshResponse->json();
                $credentials->access_token = $newTokenData['access_token'];
                $credentials->refresh_token = $newTokenData['refresh_token'] ?? $credentials->refresh_token;
                $credentials->expires_at = now()->addSeconds($newTokenData['expires_in']);
                $credentials->updated_at = now();
                $credentials->save();
            }
        } catch (\Exception $e) {
            Log::error("Error refreshing token", [
                'client_id' => $clientId,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Error al refrescar token: ' . $e->getMessage(),
            ], 500);
        }

        $userResponse = Http::withToken($credentials->access_token)
            ->get('https://api.mercadolibre.com/users/me');

        if ($userResponse->failed()) {
            Log::error("Failed to get user info", [
                'client_id' => $clientId,
                'response' => $userResponse->json()
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo obtener el ID del usuario.',
                'error' => $userResponse->json(),
            ], 500);
        }

        $userId = $userResponse->json()['id'];
        $productsStock = [];
        $processedIds = [];
        $successCount = 0;
        $errorCount = 0;
        $hasMore = true;
        $scrollId = null;

        try {
            $client = new Client([
                'timeout' => 30,
                'headers' => [
                    'Authorization' => 'Bearer ' . $credentials->access_token
                ]
            ]);

            $initialResponse = Http::withToken($credentials->access_token)
                ->get("https://api.mercadolibre.com/users/{$userId}/items/search", [
                    'search_type' => 'scan',
                    'limit' => $this->batchSize
                ]);

            if ($initialResponse->failed()) {
                Log::error("Initial scan request failed", [
                    'response' => $initialResponse->json()
                ]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Error al iniciar la búsqueda de productos',
                    'details' => $initialResponse->json()
                ], 500);
            }

            $initialData = $initialResponse->json();
            $totalItems = $initialData['paging']['total'] ?? 0;
            $scrollId = $initialData['scroll_id'] ?? null;
            $processedCount = 0;
            $retryCount = 0;
            $maxRetries = 3;
            $emptyBatchCount = 0;
            $maxEmptyBatches = 5;

            Log::info("Iniciando obtención de productos con scan", [
                'total_productos' => $totalItems,
                'scroll_id' => $scrollId
            ]);

            while ($processedCount < $totalItems && $retryCount < $maxRetries && $emptyBatchCount < $maxEmptyBatches) {
                try {
                    $response = Http::withToken($credentials->access_token)
                        ->get("https://api.mercadolibre.com/users/{$userId}/items/search", [
                            'search_type' => 'scan',
                            'scroll_id' => $scrollId,
                            'limit' => $this->batchSize
                        ]);

                    if ($response->failed()) {
                        Log::error("Error en solicitud de scroll", [
                            'scroll_id' => $scrollId,
                            'response' => $response->json(),
                            'retry_count' => $retryCount
                        ]);
                        $retryCount++;
                        usleep(50000); // Reducido de 100ms a 50ms
                        continue;
                    }

                    $data = $response->json();
                    $items = $data['results'] ?? [];
                    $scrollId = $data['scroll_id'] ?? null;

                    if (empty($items)) {
                        $emptyBatchCount++;
                        if ($processedCount < $totalItems) {
                            Log::warning("Lote vacío recibido", [
                                'processed' => $processedCount,
                                'total' => $totalItems,
                                'empty_batches' => $emptyBatchCount,
                                'retry_count' => $retryCount
                            ]);
                            
                            if ($emptyBatchCount >= 3) {
                                // Intentar obtener nuevo scroll_id después de varios lotes vacíos
                                $newScrollResponse = Http::withToken($credentials->access_token)
                                    ->get("https://api.mercadolibre.com/users/{$userId}/items/search", [
                                        'search_type' => 'scan',
                                        'limit' => $this->batchSize
                                    ]);
                                
                                if ($newScrollResponse->successful()) {
                                    $newData = $newScrollResponse->json();
                                    $newScrollId = $newData['scroll_id'] ?? null;
                                    if ($newScrollId && $newScrollId !== $scrollId) {
                                        Log::info("Nuevo scroll_id obtenido", ['scroll_id' => $newScrollId]);
                                        $scrollId = $newScrollId;
                                        $emptyBatchCount = 0; // Reset empty batch count
                                        $retryCount = 0; // Reset retry count
                                    }
                                }
                            }
                            usleep(50000); // Reducido de 100ms a 50ms
                            continue;
                        }
                        break; // Si ya procesamos todo, salir del loop
                    }

                    // Reset counters on successful batch
                    $emptyBatchCount = 0;
                    $retryCount = 0;

                    // Procesar items en paralelo
                    $promises = [];
                    foreach ($items as $item) {
                        if (!in_array($item, $processedIds)) {
                            $promises[] = $client->getAsync("https://api.mercadolibre.com/items/{$item}");
                            $processedIds[] = $item;
                        }
                    }

                    if (empty($promises)) {
                        $emptyBatchCount++;
                        continue;
                    }

                    $results = Promise\Utils::settle($promises)->wait();
                    $batchSuccess = 0;
                    foreach ($results as $result) {
                        if ($result['state'] === 'fulfilled') {
                            $itemData = json_decode($result['value']->getBody(), true);
                            if ($itemData['available_quantity'] <= 5) {
                                $productsStock[] = [
                                    'id' => $itemData['id'],
                                    'title' => $itemData['title'],
                                    'stock' => $itemData['available_quantity'],
                                    'price' => $itemData['price'],
                                    'permalink' => $itemData['permalink'],
                                    'thumbnail' => $itemData['thumbnail']
                                ];
                            }
                            $batchSuccess++;
                            $successCount++;
                        } else {
                            $errorCount++;
                            Log::error("Error al obtener detalles del item", [
                                'error' => $result['reason']->getMessage()
                            ]);
                        }
                    }

                    if ($batchSuccess === 0) {
                        $emptyBatchCount++;
                    }

                    $processedCount = count($processedIds);
                    $percentage = ($processedCount / $totalItems) * 100;

                    Log::info("Progreso de procesamiento", [
                        'processed' => $processedCount,
                        'total' => $totalItems,
                        'percentage' => round($percentage, 2) . '%',
                        'success' => $successCount,
                        'errors' => $errorCount,
                        'empty_batches' => $emptyBatchCount
                    ]);

                    usleep(250000); // Reducido de 500ms a 250ms entre lotes

                } catch (\Exception $e) {
                    Log::error("Error en el procesamiento del lote", [
                        'error' => $e->getMessage(),
                        'retry_count' => $retryCount,
                        'empty_batches' => $emptyBatchCount
                    ]);
                    $retryCount++;
                    usleep(50000); // Reducido de 100ms a 50ms antes de reintentar
                }
            }

            if ($processedCount < $totalItems) {
                Log::warning("Proceso terminado sin completar todos los productos", [
                    'processed' => $processedCount,
                    'total' => $totalItems,
                    'empty_batches' => $emptyBatchCount,
                    'retries' => $retryCount
                ]);
            }

            $responseData = [
                'total_items_processed' => count($processedIds),
                'success_count' => $successCount,
                'error_count' => $errorCount,
                'products_count' => count($productsStock),
                'productos' => $productsStock,
                'details' => [
                    'total_available' => $totalItems,
                    'completion_percentage' => round((count($processedIds) / $totalItems) * 100, 2) . '%'
                ]
            ];

            Log::info("Proceso completado", [
                'total_products' => $totalItems,
                'processed' => $processedCount,
                'critical_stock' => count($productsStock),
                'success' => $successCount,
                'errors' => $errorCount,
                'empty_batches' => $emptyBatchCount
            ]);

            Cache::put($cacheKey, $responseData, now()->addMinutes(10));

            if ($excel == true) {
                return $this->reportStockCriticExcel($responseData);
            } else if ($mail) {
                return $this->reportStockCriticMail($responseData, $mail);
            } else {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Productos obtenidos correctamente',
                    'data' => $responseData,
                    'from_cache' => false
                ], 200);
            }

        } catch (\Exception $e) {
            Log::error("General error in getStockCritic", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
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
                // Crear directorio para asegurar que existe (con permisos adecuados)
                $directoryPath = storage_path('app/reports');
                if (!File::isDirectory($directoryPath)) {
                    File::makeDirectory($directoryPath, 0755, true, true);
                }

                $fileName = 'reports/stock_critico_' . date('Ymd_His') . '.xlsx';
                $fullPath = storage_path('app/' . $fileName);

                // Crear el archivo Excel con PhpSpreadsheet
                $spreadsheet = $this->createStockCriticoSpreadsheet($result);
                $writer = new Xlsx($spreadsheet);

                // Guardar el archivo en el storage
                $writer->save($fullPath);

                // Verificar que el archivo existe
                if (!file_exists($fullPath)) {
                    throw new \Exception("No se pudo generar el archivo Excel en la ruta: " . $fullPath);
                }

                // Enviar por correo electrónico con el archivo adjunto
                Mail::to($email)->send(new StockCriticoReport($fileName));

                // Registrar el envío correcto
                Log::info('Reporte enviado exitosamente a: ' . $email . ', archivo: ' . $fullPath);

                // Eliminar el archivo después de enviar el correo (opcional, puedes mantenerlo si necesitas)
                if (file_exists($fullPath)) {
                    unlink($fullPath);
                    Log::info('Archivo Excel eliminado después de enviar el correo: ' . $fullPath);
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
            Log::error('Error al generar o enviar mail: ' . $e->getMessage());
            Log::error('Traza del error: ' . $e->getTraceAsString());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al procesar los datos y enviar mail: ' . $e->getMessage(),
                'file_path' => storage_path('app/reports'),
                'directory_exists' => File::isDirectory(storage_path('app/reports')),
                'storage_writable' => is_writable(storage_path('app'))
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

                // Crear la respuesta con el archivo como contenido para descarga directa
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
            $sheet->setCellValue('C' . $row, $producto['stock']);
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

            // aplicar color rojo cuando stock es menor o igual a 2
            if ($producto['stock'] <= 2) {
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
            throw new \Exception("El archivo adjunto no existe en la ruta: " . $filePath);
        }

        Log::info("Adjuntando archivo desde: " . $filePath);

        return $this->subject('Reporte de Stock Crítico - ' . date('d/m/Y'))
            ->view('emails.stock_report')
            ->attach($filePath, [
                'as' => 'stock_critico_' . date('Ymd') . '.xlsx',
                'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
    }
}
