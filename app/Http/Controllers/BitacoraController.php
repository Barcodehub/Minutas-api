<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\BitacoraExport;
use Illuminate\Support\Facades\Log;
use App\Models\Bitacora;
use App\Models\Evento;
use App\Models\Emergency;
use App\Models\Vehiculo;
use App\Models\BitacoraAsunto;
use App\Models\Empleado;
use App\Models\Asunto;
use App\Models\Persona;
use App\Models\VehiculoElemento;

class BitacoraController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $bitacoras = Bitacora::all();

        // Obtener todos los asuntos y empleados necesarios en una sola consulta
        $asuntos = Asunto::whereIn('id', $bitacoras->pluck('id_asunto'))->get()->keyBy('id');
        $empleados = Empleado::whereIn('id', $bitacoras->pluck('id_usuario_sesion'))->get()->keyBy('id');
        $personas = Persona::whereIn('id', $empleados->pluck('id_persona'))->get()->keyBy('id');

        foreach ($bitacoras as $bitacora) {
            // Decodificar el atributo JSON una vez
            $bitacora->atributos = json_decode($bitacora->atributos);

            // Validar que 'atributos' tenga la estructura esperada antes de usarlo
            if ($bitacora->id_asunto == 2) { // Entrada de personal
                if (isset($bitacora->atributos->employee->person)) {
                    $bitacora->descripcion = "Entrada de " . $bitacora->atributos->employee->person;
                } else {
                    $bitacora->descripcion = "Datos de empleado no disponibles.";
                }
            } elseif ($bitacora->id_asunto == 3) { // Salida de personal
                if (isset($bitacora->atributos->employee->person)) {
                    $bitacora->descripcion = "Salida de " . $bitacora->atributos->employee->person;
                } else {
                    $bitacora->descripcion = "Datos de empleado no disponibles.";
                }
            }

            // Asignar el asunto desde el array preconsultado
            $bitacora->asunto = $asuntos[$bitacora->id_asunto] ?? null;

            // Asignar el usuario de sesión desde el array preconsultado y añadir persona
            $usuarioSesion = $empleados[$bitacora->id_usuario_sesion] ?? null;
            if ($usuarioSesion) {
                $usuarioSesion->persona = $personas[$usuarioSesion->id_persona] ?? null;
            }
            $bitacora->usuario_sesion = $usuarioSesion;

            // Remover los campos que ya no se necesitan
            unset($bitacora->id_asunto, $bitacora->id_usuario_sesion, $usuarioSesion->id_persona);
        }

        return $bitacoras;
    }




    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        try {
            // Validar datos básicos del request
            $idAsunto = $request->input('idAsunto');
            $idUsuarioSesion = Empleado::find($request->input('idUsuarioSesion'));
            $asunto = Asunto::find($idAsunto);

            if ($idUsuarioSesion && $asunto) {
                $bitacora = new Bitacora();
                $bitacora->fecha = $request->input('fecha');
                $bitacora->hora = $request->input('hora');
                $bitacora->descripcion = $request->input('descripcion');
                $bitacora->id_usuario_sesion = $request->input('idUsuarioSesion');
                $bitacora->id_asunto = $idAsunto;

                // Manejo específico para entrada/salida de máquina
                if (in_array($asunto->nombre, ['Entrada de máquina', 'Salida de máquina'])) {
                    $atributos = $request->input('atributos');

                    // Validar que atributos sea un array y contenga los datos requeridos
                    if (!is_array($atributos) || !isset($atributos['vehicle']['id']) || !isset($atributos['newElements'])) {
                        throw new \Exception('Datos inválidos para vehículo.');
                    }

                    $idVehiculo = $atributos['vehicle']['id'];
                    $newElements = $atributos['newElements'];

                    // Actualizar elementos del vehículo
                    $this->actualizarElementosVehiculo($idVehiculo, $newElements);

                    // Guardar atributos como JSON
                    $bitacora->atributos = json_encode($atributos);
                } else {
                    $bitacora->atributos = json_encode($request->input('atributos'));
                }

                $bitacora->save();

                // Manejo adicional para emergencias
                if ($idAsunto == 1) {
                    $this->crearEmergency(
                        $request->input('atributos.event.id'),
                        $request->input('atributos.state'),
                        $request->input('descripcion')
                    );
                }

                return response()->json($bitacora, 201);
            }

            return response()->json(['error' => 'Datos faltantes o inválidos'], 400);
        } catch (\Exception $e) {
            // Registrar el error en los logs con información adicional
            Log::error('Error al guardar el registro de bitácora: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'exception_trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Error al guardar el registro: ' . $e->getMessage()], 500);
        }
    }






    private function actualizarElementosVehiculo($idVehiculo, $newElements)
    {
        try {
            // Validar que $newElements sea un array válido
            if (!is_array($newElements)) {
                Log::error('Datos inválidos para actualizar elementos del vehículo', [
                    'idVehiculo' => $idVehiculo,
                    'newElements' => $newElements,
                ]);
                throw new \Exception('Los elementos proporcionados no son válidos.');
            }

            // Eliminar elementos existentes
            VehiculoElemento::where('id_vehiculo', $idVehiculo)->delete();

            // Crear nuevos elementos
            foreach ($newElements as $element) {
                VehiculoElemento::create([
                    'id_elemento' => $element['id'],
                    'id_vehiculo' => $idVehiculo,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error en actualizarElementosVehiculo: ' . $e->getMessage(), [
                'idVehiculo' => $idVehiculo,
                'newElements' => $newElements,
            ]);
            throw $e;
        }
    }


    public function validateEntryExit(Request $request)
    {
        try {
            // IDs específicos para Entrada y Salida de personal
            $idEntrada = 2; // Entrada de personal
            $idSalida = 3;  // Salida de personal

            // Obtener datos del empleado y el asunto
            $idEmpleado = $request->input('idEmpleado');  // ID del empleado a validar
            $idAsunto = $request->input('idAsunto');      // Tipo de registro (Entrada o Salida)

            // Verificar que los datos necesarios estén presentes
            if (!$idEmpleado || !$idAsunto) {
                return response()->json(['error' => 'Datos incompletos: se requiere idEmpleado y idAsunto.'], 400);
            }

            // Obtener el último registro del empleado en cuestión
            $ultimoRegistro = Bitacora::where('atributos->employee->id', $idEmpleado) // Buscar en atributos JSON
                ->whereIn('id_asunto', [$idEntrada, $idSalida])
                ->latest('id')
                ->first();

            // Validación para Entrada
            if ($idAsunto == $idEntrada) { // Entrada de personal
                if ($ultimoRegistro && $ultimoRegistro->id_asunto == $idEntrada) {
                    return response()->json(['error' => 'El empleado ya tiene una entrada registrada sin una salida correspondiente.'], 400);
                }
            }
            // Validación para Salida
            elseif ($idAsunto == $idSalida) { // Salida de personal
                if (!$ultimoRegistro || $ultimoRegistro->id_asunto != $idEntrada) {
                    return response()->json(['error' => 'El empleado no tiene una entrada registrada previamente.'], 400);
                }
            }
            // Si el asunto no es ni entrada ni salida
            else {
                return response()->json(['error' => 'El asunto no es válido para esta operación.'], 400);
            }

            // Validación exitosa
            return response()->json(['success' => 'Validación exitosa.'], 200);
        } catch (\Exception $e) {
            // Capturar y devolver errores del servidor
            return response()->json(['error' => 'Error durante la validación: ' . $e->getMessage()], 500);
        }
    }

    public function getAvailableEmployees()
    {
        Log::info('Entrando al método getAvailableEmployees');

        try {
            $idEntrada = 2; // Entrada de personal
            $idSalida = 3;  // Salida de personal

            // Obtener los últimos registros de cada empleado
            $ultimosRegistros = Bitacora::whereIn('id_asunto', [$idEntrada, $idSalida])
                ->orderBy('id', 'desc') // Ordenar por ID para obtener el último registro primero
                ->get()
                ->groupBy(function ($registro) {
                    $atributos = json_decode($registro->atributos, true);
                    return $atributos['employee']['id'] ?? null; // Agrupar por ID del empleado
                });

            $empleadosDisponibles = [];

            foreach ($ultimosRegistros as $idEmpleado => $registros) {
                if (!$idEmpleado) {
                    continue; // Ignorar si no tiene un ID de empleado
                }

                // Obtener el último registro de este empleado
                $ultimoRegistro = $registros->first();

                // Decodificar atributos JSON
                $atributos = json_decode($ultimoRegistro->atributos, true);

                // Verificar si el último registro es de entrada
                if ($ultimoRegistro->id_asunto == $idEntrada && isset($atributos['employee'])) {
                    $empleadosDisponibles[] = $atributos['employee'];
                }
            }

            Log::info('Empleados disponibles:', $empleadosDisponibles);

            // Retornar respuesta JSON con los empleados disponibles
            return response()->json($empleadosDisponibles, 200);
        } catch (\Exception $e) {
            Log::error('Error en getAvailableEmployees:', ['message' => $e->getMessage()]);
            return response()->json(['error' => 'Error al obtener empleados disponibles: ' . $e->getMessage()], 500);
        }
    }

    public function getAvailableMachines()
    {
        Log::info('Entrando al método getAvailableMachines');

        try {
            $idEntrada = 4; // Entrada de máquina
            $idSalida = 5;  // Salida de máquina

            // Obtener los últimos registros de cada máquina
            $ultimosRegistros = Bitacora::whereIn('id_asunto', [$idEntrada, $idSalida])
                ->orderBy('id', 'desc') // Ordenar por ID para obtener el último registro primero
                ->get()
                ->groupBy(function ($registro) {
                    $atributos = json_decode($registro->atributos, true);
                    return $atributos['vehicle']['id'] ?? null; // Agrupar por ID del vehículo
                });

            $maquinasDisponibles = [];

            foreach ($ultimosRegistros as $idVehiculo => $registros) {
                if (!$idVehiculo) {
                    continue; // Ignorar si no tiene un ID de vehículo
                }

                // Obtener el último registro de esta máquina
                $ultimoRegistro = $registros->first();

                // Decodificar atributos JSON
                $atributos = json_decode($ultimoRegistro->atributos, true);

                // Verificar si el último registro es de entrada
                if ($ultimoRegistro->id_asunto == $idEntrada && isset($atributos['vehicle'])) {
                    $maquinasDisponibles[] = $atributos['vehicle'];
                }
            }

            Log::info('Máquinas disponibles:', $maquinasDisponibles);

            // Retornar respuesta JSON con las máquinas disponibles
            return response()->json($maquinasDisponibles, 200);
        } catch (\Exception $e) {
            Log::error('Error en getAvailableMachines:', ['message' => $e->getMessage()]);
            return response()->json(['error' => 'Error al obtener máquinas disponibles: ' . $e->getMessage()], 500);
        }
    }




    public function validateMachineEntry(Request $request)
    {
        Log::info('Entrando al método validateMachineEntry');

        try {
            $idEntrada = 4; // ID de entrada de máquina
            $idSalida = 5;  // ID de salida de máquina

            // Obtener datos de la máquina del request
            $idVehiculo = $request->input('idVehiculo'); // ID del vehículo a validar

            // Verificar que los datos necesarios estén presentes
            if (!$idVehiculo) {
                return response()->json(['error' => 'Datos incompletos: se requiere idVehiculo.'], 400);
            }

            // Obtener el último registro de la máquina
            $ultimoRegistro = Bitacora::where('atributos->vehicle->id', $idVehiculo) // Buscar en los atributos JSON
                ->whereIn('id_asunto', [$idEntrada, $idSalida]) // Filtrar por entradas y salidas de máquina
                ->latest('id') // Obtener el registro más reciente
                ->first();

            // Validar si el último registro fue una entrada
            if ($ultimoRegistro && $ultimoRegistro->id_asunto == $idEntrada) {
                return response()->json(['error' => 'El vehículo ya tiene una entrada registrada sin una salida correspondiente.'], 400);
            }

            // Si no hay un registro de entrada sin salida, permitir la entrada
            return response()->json(['success' => 'Validación exitosa.'], 200);
        } catch (\Exception $e) {
            Log::error('Error en validateMachineEntry:', ['message' => $e->getMessage()]);
            return response()->json(['error' => 'Error al validar entrada de máquina: ' . $e->getMessage()], 500);
        }
    }





    public function visitorsWithPendingExit()
    {
        try {
            $idEntrada = 6; // ID para Entrada visitante
            $idSalida = 7;  // ID para Salida visitante

            // Obtener todas las entradas y salidas
            $entradas = Bitacora::where('id_asunto', $idEntrada)->get();
            $salidas = Bitacora::where('id_asunto', $idSalida)->get();

            // Validar que hay datos en las entradas y salidas
            if ($entradas->isEmpty()) {
                return response()->json(['error' => 'No se encontraron entradas de visitantes.'], 404);
            }

            // Procesar los pendientes
            $pendientes = $entradas->filter(function ($entrada) use ($salidas) {
                $entradaAtributos = json_decode($entrada->atributos, true);

                // Validar que la estructura de los atributos sea correcta
                if (!isset($entradaAtributos['name'], $entradaAtributos['document'], $entradaAtributos['phone'])) {
                    return false; // Saltar entradas mal formateadas
                }

                // Revisar si existe una salida correspondiente
                return !$salidas->contains(function ($salida) use ($entradaAtributos) {
                    $salidaAtributos = json_decode($salida->atributos, true);

                    // Comparar atributos de entrada y salida
                    return isset($salidaAtributos['name'], $salidaAtributos['document'], $salidaAtributos['phone']) &&
                        $salidaAtributos['name'] === $entradaAtributos['name'] &&
                        $salidaAtributos['document'] === $entradaAtributos['document'] &&
                        $salidaAtributos['phone'] === $entradaAtributos['phone'];
                });
            });

            // Retornar los visitantes pendientes
            return response()->json($pendientes->values(), 200);
        } catch (\Exception $e) {
            // Capturar errores y retornar una respuesta amigable
            return response()->json(['error' => 'Error al obtener visitantes pendientes: ' . $e->getMessage()], 500);
        }
    }




    public function validateMachineEntryExit(Request $request)
    {
        try {
            // IDs específicos para Entrada y Salida de máquina
            $idEntrada = 4; // Entrada de máquina
            $idSalida = 5;  // Salida de máquina

            // Obtener datos del vehículo y el asunto
            $idVehiculo = $request->input('idVehiculo');  // ID del vehículo a validar
            $idAsunto = $request->input('idAsunto');      // Tipo de registro (Entrada o Salida)

            // Verificar que los datos necesarios estén presentes
            if (!$idVehiculo || !$idAsunto) {
                return response()->json(['error' => 'Datos incompletos: se requiere idVehiculo y idAsunto.'], 400);
            }

            // Obtener el último registro del vehículo en cuestión
            $ultimoRegistro = Bitacora::where('atributos->vehicle->id', $idVehiculo) // Buscar en atributos JSON
                ->whereIn('id_asunto', [$idEntrada, $idSalida])
                ->latest('id')
                ->first();

            // Validación para Entrada
            if ($idAsunto == $idEntrada) { // Entrada de máquina
                if ($ultimoRegistro && $ultimoRegistro->id_asunto == $idEntrada) {
                    return response()->json(['error' => 'El vehículo ya tiene una entrada registrada sin una salida correspondiente.'], 400);
                }
            }
            // Validación para Salida
            elseif ($idAsunto == $idSalida) { // Salida de máquina
                if (!$ultimoRegistro || $ultimoRegistro->id_asunto != $idEntrada) {
                    return response()->json(['error' => 'El vehículo no tiene una entrada registrada previamente.'], 400);
                }
            }
            // Si el asunto no es ni entrada ni salida
            else {
                return response()->json(['error' => 'El asunto no es válido para esta operación.'], 400);
            }

            // Validación exitosa
            return response()->json(['success' => 'Validación exitosa.'], 200);
        } catch (\Exception $e) {
            // Capturar y devolver errores del servidor
            return response()->json(['error' => 'Error durante la validación: ' . $e->getMessage()], 500);
        }
    }



    // Métodos auxiliares para mejorar la legibilidad y reutilización del código

    private function convertirId($input)
    {
        return intval(substr($input, 1));
    }

    private function procesarSocketEntrada($message, $host, $port)
    {
        $socket = socket_create(AF_INET, SOCK_STREAM, 0);
        if (!$socket || !socket_connect($socket, $host, $port)) {
            throw new \Exception("No se pudo conectar con el servidor de huellas.");
        }
        socket_write($socket, $message . "\n", strlen($message) + 1);
        $result = socket_read($socket, 1024);
        socket_close($socket);

        return substr($result, 2);
    }




    private function crearEmergency($eventoId, $estado, $descripcion)
    {
        $evento = Evento::find($eventoId);
        if ($evento) {
            $emergency = new Emergency;
            $emergency->tipoEmergency = $evento->nombre;
            $emergency->idMinuta = Bitacora::latest()->first()->id;
            $emergency->estado = $estado;
            $emergency->descripcion = $descripcion;
            $emergency->save();
        }
    }


    public function bitacoraPersonal(Request $request)
    {
        $asunto = Asunto::find($request['idAsunto']);
        $bitacoras = Bitacora::where('bitacora.id_asunto', '=', $asunto->id)
            ->get();
        foreach ($bitacoras as $bitacora) {
            $bitacora->atributos = json_decode($bitacora->atributos);
            $usuarioSesion = Empleado::find($bitacora->id_usuario_sesion);
            $persona = Persona::find($usuarioSesion->id_persona);
            $bitacora->asunto = $asunto; // esta definido arriba
            $usuarioSesion->persona = $persona;
            $bitacora->usuario_sesion = $usuarioSesion;
            unset($bitacora->id_asunto);
            unset($bitacora->id_usuario_sesion);
            unset($usuarioSesion->id_persona);
        }
        if ($asunto->nombre == 'Entrada de personal' || $asunto->nombre == 'Salida de personal') {
            $bitacorasSeleccionadas = [];
            foreach ($bitacoras as $bitacora) {
                if ($bitacora->atributos->employee->id == $request['idEmpleado']) {
                    array_push($bitacorasSeleccionadas, $bitacora);
                }
            }
            return $bitacorasSeleccionadas;
        } else if ($asunto->nombre == 'Relevo') {
            $bitacorasSeleccionadas = [];
            foreach ($bitacoras as $bitacora) {
                if ($bitacora->atributos->employeeDelivery->id == $request['idEmpleado'] || $bitacora->atributos->employeeReceives->id == $request['idEmpleado']) {
                    array_push($bitacorasSeleccionadas, $bitacora);
                }
            }
            return $bitacorasSeleccionadas;
        } else if ($asunto->nombre == 'Entrada de máquina' || $asunto->nombre == 'Salida de máquina' || $asunto->nombre == 'Novedades en máquinas') {
            $bitacorasSeleccionadas = [];
            foreach ($bitacoras as $bitacora) {
                if ($bitacora->atributos->vehicle->id == $request['idVehiculo']) {
                    array_push($bitacorasSeleccionadas, $bitacora);
                }
            }
            return $bitacorasSeleccionadas;
        }
        return $bitacoras;
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $bitacora = Bitacora::find($id);
        if ($bitacora) {
            $bitacora->atributos = json_decode($bitacora->atributos);
            $asunto = Asunto::find($bitacora->id_asunto);
            $usuarioSesion = Empleado::find($bitacora->id_usuario_sesion);
            $persona = Persona::find($usuarioSesion->id_persona);
            $usuarioSesion->persona = $persona;
            $bitacora->asunto = $asunto;
            $bitacora->usuario_sesion = $usuarioSesion;
            unset($bitacora->id_asunto);
            unset($bitacora->id_usuario_sesion);
            unset($usuarioSesion->id_persona);
            return $bitacora;
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
    $bitacora = Bitacora::find($id);
    $idUsuarioSesion = $request['idUsuarioSesion'];
    $idAsunto = $request['idAsunto'];
    $validEmpleado = isset($idUsuarioSesion) ? (Empleado::find($idUsuarioSesion) ? true : false) : true;
    $validAsunto = isset($idAsunto) ? (Asunto::find($idAsunto) ? true : false) : true;

    if ($bitacora && $validEmpleado && $validAsunto) {
        $bitacora->fecha = $request['fecha'] ?? $bitacora->fecha;
        $bitacora->hora = $request['hora'] ?? $bitacora->hora;
        $bitacora->descripcion = $request['descripcion'] ?? $bitacora->descripcion;

        $asunto = Asunto::find($idAsunto);
        if ($request['atributos'] && ($asunto->nombre == 'Entrada de máquina' || $asunto->nombre == 'Salida de máquina')) {
            $idVehiculo = $request['atributos']['vehicle']['id'];
            $requestElementos = $request['atributos']['newElements'];

            VehiculoElemento::where('id_vehiculo', $idVehiculo)->delete(); // Elimina elementos antiguos
            foreach ($requestElementos as $requestElemento) {
                VehiculoElemento::create([
                    'id_elemento' => $requestElemento['id'],
                    'id_vehiculo' => $idVehiculo,
                ]);
            }
            $bitacora->atributos = json_encode($request['atributos']);
        } else {
            $bitacora->atributos = $request['atributos'] ?? $bitacora->atributos;
        }

        $bitacora->id_usuario_sesion = $idUsuarioSesion ?? $bitacora->id_usuario_sesion;
        $bitacora->id_asunto = $idAsunto ?? $bitacora->id_asunto;
        $bitacora->save();

        // Manejo de Emergencias
        if ($idAsunto == 1) { // Supongo que "1" representa "Recepción de emergencia"
            $evento = Evento::find($request['atributos']['event']['id']);
            $existingEmergency = Emergency::where('idMinuta', $bitacora->id)->first();

            if ($existingEmergency) {
                // Actualiza la emergencia existente
                $existingEmergency->tipoEmergency = $evento->nombre ?? $existingEmergency->tipoEmergency;
                $existingEmergency->estado = $request['atributos']['state'] ?? $existingEmergency->estado;
                $existingEmergency->descripcion = $request['descripcion'] ?? $existingEmergency->descripcion;
                $existingEmergency->save();

                // Si la emergencia se marca como terminada, liberar los vehículos asociados
                if ($existingEmergency->estado === 'Terminada') {
                    Vehiculo::where('idEmergency', $existingEmergency->id)
                        ->update(['idEmergency' => null]);
                }
            } else {
                // Crea una nueva emergencia si no existe
                $emergency = new Emergency;
                $emergency->tipoEmergency = $evento->nombre;
                $emergency->idMinuta = $bitacora->id;
                $emergency->estado = $request['atributos']['state'];
                $emergency->descripcion = $request['descripcion'];
                $emergency->save();
            }
        }

        return $bitacora;
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
        $bitacora = Bitacora::find($id);
        if ($bitacora) {
            $bitacora->delete();
            return $bitacora;
        }
        return [];
    }

    public function excelReport()
    {
        return new BitacoraExport();
    }
}
