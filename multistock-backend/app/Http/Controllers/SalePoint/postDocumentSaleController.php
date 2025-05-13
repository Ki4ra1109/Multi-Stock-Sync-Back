<?php

namespace App\Http\Controllers\SalePoint;

use App\Http\Controllers\Controller;
use App\Models\Document_Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class postDocumentSaleController extends Controller
{
    public function postDocumentSale(Request $request)
    {
        $request->validate([
            'id_folio' => 'required|integer',
            'documento' => 'required|file|mimes:pdf|max:16000', // 16MB
        ]);

        // Leer el contenido binario del archivo
        $binaryContent = file_get_contents($request->file('documento')->getRealPath());

        // Crear el registro en la base de datos
        $document = Document_Sale::create([
            'id_folio' => $request->id_folio,
            'documento' => $binaryContent,
        ]);

        return response()->json([
            'message' => 'Documento de venta guardado exitosamente.',
            'data' => [
                'id' => $document->id,
                'id_folio' => $document->id_folio,
                'created_at' => $document->created_at,
            ],
        ], 201);
    }
}