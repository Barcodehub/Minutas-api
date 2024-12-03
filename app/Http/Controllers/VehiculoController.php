<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\VehiculoExport;

use App\Models\Vehiculo;
use App\Models\TipoVehiculo;
use App\Models\VehiculoElemento;

class VehiculoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $vehiculos = Vehiculo::all();
        foreach ($vehiculos as $vehiculo) {
            $elementos = VehiculoElemento::join('elemento', 'vehiculo_elemento.id_elemento', '=', 'elemento.id')
                ->where('vehiculo_elemento.id_vehiculo', '=', $vehiculo->id)
                ->select('elemento.*')
                ->get();
            $vehiculo->elementos = $elementos;
            $tipoVehiculo = TipoVehiculo::find($vehiculo->id_tipo_vehiculo);
            $vehiculo->tipo_vehiculo = $tipoVehiculo;
            unset($vehiculo->id_tipo_vehiculo);
        }
        return $vehiculos;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $idTipoVehiculo = TipoVehiculo::find($request['idTipoVehiculo']);
        $elementos = $request['elementos'];
        if ($idTipoVehiculo) {
            $vehiculo = new Vehiculo;
            // $vehiculo->id = $vehiculo->id;
            $vehiculo->nombre = $request['nombre'];
            $vehiculo->marca = $request['marca'];
            $vehiculo->modelo = $request['modelo'];
            $vehiculo->placa = $request['placa'];
            $vehiculo->id_tipo_vehiculo = $request['idTipoVehiculo'];
            $vehiculo->save();
            foreach ($elementos as $elemento) {
                $vehiculoElemento = new VehiculoElemento;
                $vehiculoElemento->id_elemento = $elemento['id'];
                $vehiculoElemento->id_vehiculo = $vehiculo->id;
                $vehiculoElemento->save();
            }
            return $vehiculo;
        }
        return response('Datos faltantes', 400)->header('Content-Type', 'text/plain');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $vehiculo = Vehiculo::find($id);
        if ($vehiculo) {
            $elementos = VehiculoElemento::join('elemento', 'vehiculo_elemento.id_elemento', '=', 'elemento.id')
                ->where('vehiculo_elemento.id_vehiculo', '=', $id)
                ->select('elemento.*')
                ->get();
            $vehiculo->elementos = $elementos;
            $tipoVehiculo = TipoVehiculo::find($vehiculo->id_tipo_vehiculo);
            $vehiculo->tipo_vehiculo = $tipoVehiculo;
            unset($vehiculo->id_tipo_vehiculo);
            return $vehiculo;
        }
        return response('Registro no encontrado', 400)->header('Content-Type', 'text/plain');
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
        $vehiculo = Vehiculo::find($id);
        $idTipoVehiculo = $request['idTipoVehiculo'];
        $validTipoVehiculo = isset($idTipoVehiculo) ? (TipoVehiculo::find($idTipoVehiculo) ? true : false) : true;
        if ($vehiculo && $validTipoVehiculo) {
            $vehiculo->id = $vehiculo->id;
            $vehiculo->nombre = isset($request['nombre']) ? $request['nombre'] : $vehiculo->nombre;
            $vehiculo->marca = isset($request['marca']) ? $request['marca'] : $vehiculo->marca;
            $vehiculo->modelo = isset($request['modelo']) ? $request['modelo'] : $vehiculo->modelo;
            $vehiculo->placa = isset($request['placa']) ? $request['placa'] : $vehiculo->placa;
            $vehiculo->id_tipo_vehiculo = isset($request['idTipoVehiculo']) ? $request['idTipoVehiculo'] : $vehiculo->id_tipo_vehiculo;
            $vehiculo->save();
            $requestElementos = $request['elementos'];
            $elementos = VehiculoElemento::where('vehiculo_elemento.id_vehiculo', '=', $id)
                ->get();
            foreach ($elementos as $elemento) {
                $elemento->delete();
            }
            foreach ($requestElementos as $requestElemento) {
                $vehiculoElemento = new VehiculoElemento;
                $vehiculoElemento->id_elemento = $requestElemento['id'];
                $vehiculoElemento->id_vehiculo = $id;
                $vehiculoElemento->save();
            }
            return $vehiculo;
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
        $vehiculo = Vehiculo::find($id);
        if ($vehiculo) {
            $vehiculo->delete();
            return $vehiculo;
        }
        return [];
    }

    public function excelReport()
    {
        return new VehiculoExport();
    }

    public function assignVehiclesToEmergency(Request $request, $idEmergency)
    {
        try {
            // Validar que la emergencia exista y esté en estado "En seguimiento"
            $emergency = \App\Models\Emergency::findOrFail($idEmergency);

            if ($emergency->estado !== 'En seguimiento') {
                return response()->json(['error' => 'La emergencia debe estar en seguimiento para asociar vehículos.'], 400);
            }

            // Obtener los IDs de los vehículos a asociar desde el request
            $vehicleIds = $request->input('vehicle_ids', []);

            if (empty($vehicleIds)) {
                return response()->json(['error' => 'Debe proporcionar al menos un vehículo para asociar a la emergencia.'], 400);
            }

            // Validar que los vehículos existan en la base de datos
            $vehicles = Vehiculo::whereIn('id', $vehicleIds)->get();

            if ($vehicles->count() !== count($vehicleIds)) {
                $notFoundIds = array_diff($vehicleIds, $vehicles->pluck('id')->toArray());
                return response()->json([
                    'error' => 'Algunos vehículos no existen en la base de datos.',
                    'not_found_ids' => $notFoundIds,
                ], 404);
            }

            // Validar que los vehículos no estén ya asignados a otras emergencias
            $vehiclesInUse = $vehicles->whereNotNull('idEmergency');

            if ($vehiclesInUse->isNotEmpty()) {
                return response()->json([
                    'error' => 'Algunos vehículos ya están asignados a otra emergencia.',
                    'vehicles_in_use' => $vehiclesInUse->pluck('id'),
                ], 400);
            }

            // Asociar los vehículos a la emergencia
            Vehiculo::whereIn('id', $vehicleIds)->update(['idEmergency' => $idEmergency]);

            return response()->json(['success' => 'Vehículos asignados a la emergencia con éxito.'], 200);
        } catch (\Exception $e) {
            // Manejo de errores generales
            return response()->json(['error' => 'Error al asignar vehículos: ' . $e->getMessage()], 500);
        }
    }


    public function finishEmergency(Request $request, $idEmergency)
    {
        try {
            // Validar que la emergencia exista
            $emergency = \App\Models\Emergency::findOrFail($idEmergency);

            // Verificar si la emergencia ya está terminada
            if ($emergency->estado === 'Terminada') {
                return response()->json(['error' => 'La emergencia ya está terminada.'], 400);
            }

            // Validar que la emergencia esté en estado "En seguimiento" para finalizarla
            if ($emergency->estado !== 'En seguimiento') {
                return response()->json(['error' => 'Solo las emergencias en estado "En seguimiento" pueden ser terminadas.'], 400);
            }

            // Cambiar el estado de la emergencia a "Terminada"
            $emergency->estado = 'Terminada';
            $emergency->save();

            // Obtener los vehículos asociados a esta emergencia
            $vehicles = Vehiculo::where('idEmergency', $idEmergency)->get();

            if ($vehicles->isEmpty()) {
                return response()->json(['warning' => 'La emergencia fue finalizada, pero no tenía vehículos asociados.'], 200);
            }

            // Liberar los vehículos asociados a esta emergencia
            Vehiculo::where('idEmergency', $idEmergency)->update(['idEmergency' => null]);

            return response()->json([
                'success' => 'Emergencia finalizada y vehículos liberados con éxito.',
                'released_vehicles' => $vehicles->pluck('id'),
            ], 200);
        } catch (\Exception $e) {
            // Manejo de errores generales
            return response()->json(['error' => 'Error al finalizar la emergencia: ' . $e->getMessage()], 500);
        }
    }
}
