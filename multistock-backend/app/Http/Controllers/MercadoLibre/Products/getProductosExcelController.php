<?php

namespace App\Http\Controllers\MercadoLibre\Products;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;

class getProductosExcelController extends Controller
{
    public function leerExcel(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls'
        ]);

        $file = $request->file('file');
        $spreadsheet = IOFactory::load($file->getPathname());
        $sheet = $spreadsheet->getSheetByName('Pijamas');

        if (!$sheet) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se encontró la hoja "Pijamas".'
            ], 400);
        }

        // Leer todas las celdas con claves A, B, C...
        $rows = $sheet->toArray(null, true, true, true);

        // Encabezados desde la fila 2 (índice 2)
        $headerRow = $rows[2] ?? [];

        // Limpiar encabezados vacíos
        $headers = array_filter($headerRow, function ($value) {
            return trim($value) !== '';
        });

        $productos = [];
        $productoIndex = 1;

        // Leer desde la fila 6 (índice 6)
        foreach ($rows as $filaIndex => $fila) {
            if ($filaIndex < 6) continue;

            // Evitar filas completamente vacías
            if (!array_filter($fila)) continue;

            $producto = [];

            foreach ($headers as $colLetra => $nombreCampo) {
                $producto[$nombreCampo] = $fila[$colLetra] ?? null;
            }

            // Saltar productos totalmente vacíos
            if (array_filter($producto)) {
                $productos["Producto_{$productoIndex}"] = $producto;
                $productoIndex++;
            }
        }

        return response()->json([
            'status' => 'success',
            'total' => count($productos),
            'data' => $productos
        ]);
    }
    public function redirigir()
    {
        
        $totalProductos = 100;
        $mensaje = "Carga masiva completada correctamente.";

        return view('mercadolibre.carga_masiva', [
            'totalProductos' => $totalProductos,
            'mensaje' => $mensaje
        ]);
    }
}
