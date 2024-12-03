<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\CargoExport;

use App\Models\Cargo;
use App\Models\EmpleadoNombramiento;

class CargoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return Cargo::all();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $cargo = new Cargo;
        // $cargo->id = $cargo->id;
        $cargo->nombre = $request['nombre'];
        $cargo->save();
        return $cargo;
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $cargo = Cargo::find($id);
        return isset($cargo) ? $cargo : [];
    }

    public function getAvailables($id)
    {
        // Obtener los IDs de cargos inválidos en una sola consulta
        $idsInvalidos = Cargo::leftJoin('empleado_nombramiento', function ($join) use ($id) {
            $join->on('cargo.id', '=', 'empleado_nombramiento.id_cargo')
                ->where('empleado_nombramiento.id_empleado', '=', $id);
        })
            ->where(function ($query) {
                $query->whereNull('empleado_nombramiento.id')
                    ->orWhere('empleado_nombramiento.activo', '<>', 'No');
            })
            ->pluck('cargo.id')
            ->toArray();

        // Si hay cargos inválidos, obtener todos los cargos que no están en la lista de IDs inválidos
        if (!empty($idsInvalidos)) {
            return Cargo::whereNotIn('id', $idsInvalidos)->get();
        }

        // Si no hay nombramientos, devolver todos los cargos
        return Cargo::all();
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $cargo = Cargo::find($id);
        if ($cargo) {
            $cargo->id = $cargo->id;
            $cargo->nombre = isset($request['nombre']) ? $request['nombre'] : $cargo->nombre;
            $cargo->save();
            return $cargo;
        }
        return [];
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $cargo = Cargo::find($id);
        if ($cargo) {
            $cargo->delete();
            return $cargo;
        }
        return [];
    }

    public function excelReport()
    {
        return new CargoExport();
    }
}
