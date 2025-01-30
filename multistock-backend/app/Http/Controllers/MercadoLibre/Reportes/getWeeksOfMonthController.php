<?php

namespace App\Http\Controllers\MercadoLibre\Reportes;

use App\Models\MercadoLibreCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class getWeeksOfMonthController
{

    /**
     * Get weeks of the month based on the year and month.
    */
    public function getWeeksOfMonth(Request $request)
    {
        // Get the year and month from the request
        $year = $request->query('year', date('Y')); // Default to current year
        $month = $request->query('month', date('m')); // Default to current month

        // Validate year and month
        if (!checkdate($month, 1, $year)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Fecha no válida. Por favor, proporcione un año y mes válidos.',
            ], 400);
        }

        try {
            // First day of the month
            $startOfMonth = \Carbon\Carbon::createFromDate($year, $month, 1);

            // Last day of the month
            $endOfMonth = \Carbon\Carbon::createFromDate($year, $month, 1)->endOfMonth();

            // Get the number of weeks in the month
            $weeks = [];
            $currentStartDate = $startOfMonth;

            // Loop through the month and create weeks
            while ($currentStartDate <= $endOfMonth) {
                $currentEndDate = $currentStartDate->copy()->endOfWeek();

                if ($currentEndDate > $endOfMonth) {
                    $currentEndDate = $endOfMonth; // Adjust if the week goes into the next month
                }

                $weeks[] = [
                    'start_date' => $currentStartDate->toDateString(),
                    'end_date' => $currentEndDate->toDateString(),
                ];

                // Move to the next week
                $currentStartDate = $currentEndDate->addDay();
            }

            // Filter out weeks that are not within the specified month
            $weeks = array_filter($weeks, function ($week) use ($month) {
                return \Carbon\Carbon::createFromFormat('Y-m-d', $week['start_date'])->month == $month;
            });

            // Return weeks data
            return response()->json([
                'status' => 'success',
                'message' => 'Semanas obtenidas con éxito.',
                'data' => array_values($weeks),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al procesar la solicitud.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

}