<?php

namespace App\Http\Controllers\SalePoint;

use App\Http\Controllers\Controller;
use App\Models\Document_Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;


class getDocumentByDownloadController extends Controller
{
    public function getDocumentByDownload(Request $request, $client_id, $id_folio)
    {
        
        //Validar que el documento existe
        $document = DB::Table('document_sale')
            ->join('sale', 'document_sale.id_folio', '=', 'sale.id')
            ->join('warehouses', 'sale.warehouse_id', '=', 'warehouses.id')
            ->join('companies', 'companies.id', '=', 'warehouses.assigned_company_id')
            ->where('id_folio', $id_folio)
            ->where('companies.client_id', $client_id)
            ->select('document_sale.documento')
            ->first();

        if (!$document) {
            return response()->json([
                'message' => 'El documento no existe o no pertenece a este cliente',
            ], 404);
        }

        // El contenido del archivo PDF está en el campo 'documento'
        $binaryContent = $document->documento;

        // Devolver el archivo como una descarga
        return Response::make($binaryContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="documento.pdf"',
        ]);

    }
}