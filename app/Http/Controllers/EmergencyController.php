<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\EmergencyExport;

use App\Models\Emergency;
use Illuminate\Support\Facades\DB;

class EmergencyController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return Emergency::all();
    }

    public function excelReport()
    {
        return new EmergencyExport();
    }

    public function getEmergenciesByStatus(Request $request)
    {
        try {
            // Obtener el parámetro de estado de la solicitud
            $status = $request->query('status');

            if (!$status) {
                return response()->json(['error' => 'Debe proporcionar un estado para filtrar.'], 400);
            }

            // Filtrar emergencias por estado
            $emergencies = Emergency::where('estado', $status)->get();

            return response()->json($emergencies, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener emergencias: ' . $e->getMessage()], 500);
        }
    }
}
