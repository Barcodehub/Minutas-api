<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Huella;
use App\Models\Empleado;

class HuellaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return Huella::all();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $idEmpleado = Empleado::find($request['idEmpleado']);
        if ($idEmpleado) {
            $huella = new Huella;
            // $huella->id = $huella->id;
            $huella->huella = $request['huella'];
            $huella->id_empleado = $request['idEmpleado'];
            $huella->save();
            return $huella;
        }
        return [];
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $huella = Huella::find($id);
        return isset($huella) ? $huella : [];
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
        $huella = Huella::find($id);
        $idEmpleado = $request['idEmpleado'];
        $validEmpleado = isset($idEmpleado) ? (Empleado::find($idEmpleado) ? true : false) : true;
        if ($huella && $validEmpleado) {
            $huella->id = $huella->id;
            $huella->huella = isset($request['huella']) ? $request['huella'] : $huella->huella;
            $huella->id_empleado = isset($request['idEmpleado']) ? $request['idEmpleado'] : $huella->id_empleado;
            $huella->save();
            return $huella;
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
        $huella = Huella::find($id);
        if ($huella) {
            $huella->delete();
            return $huella;
        }
        return [];
    }

    public function crearHuella($idEmpleado)
{
    $host = "host.docker.internal";  // Accede al host desde Docker
    $port = 1234;
    $message = $idEmpleado . "\n";

    // Crear el socket
    $socket = socket_create(AF_INET, SOCK_STREAM, 0);
    if ($socket === false) {
        return response()->json(['error' => 'No se pudo crear el socket'], 500);
    }

    // Conectar al servidor
    $result = socket_connect($socket, $host, $port);
    if ($result === false) {
        return response()->json(['error' => 'No se pudo conectar con el servidor'], 500);
    }

    // Enviar el mensaje
    $result = socket_write($socket, $message, strlen($message));
    if ($result === false) {
        return response()->json(['error' => 'No se pudo enviar datos al servidor'], 500);
    }

    // Leer la respuesta
    $result = socket_read($socket, 1024);
    if ($result === false) {
        return response()->json(['error' => 'No se pudo leer la respuesta del servidor'], 500);
    }

    // Cerrar el socket
    socket_close($socket);

    // Procesar la respuesta
    $huella = trim($result);  // Elimina espacios y saltos de línea

    // Buscar al empleado
    $empleado = Empleado::find($idEmpleado);
    if (!$empleado) {
        return response()->json(['error' => 'Empleado no encontrado'], 404);
    }

    // Crear o actualizar la huella del empleado
    $huellaModel = Huella::updateOrCreate(
        ['id_empleado' => $idEmpleado],  // Condición para buscar
        ['huella' => $huella]            // Datos para crear/actualizar
    );

    // Actualizar el campo "acceso_huella" del empleado
    $empleado->acceso_huella = true;  // O 'Si', dependiendo del tipo de campo
    $empleado->save();

    // Devolver la respuesta
    return response()->json([
        'respuesta' => $huella,
        'mensaje' => 'Huella creada/actualizada correctamente',
        'huella' => $huellaModel,
        'empleado' => $empleado
    ]);
}

    public function destroyForIdEmploye($idEmpleado)
    {
        $huella = Huella::where('huella.id_empleado', '=', $idEmpleado)->first();
        if ($huella) {
            $huella->delete();
            return $huella;
        }
        return [];
    }
}
