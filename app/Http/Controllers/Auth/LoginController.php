<?php

namespace App\Http\Controllers\Auth;

use Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use App\Models\Empleado;
use App\Models\Persona;
use App\Models\TipoUsuario;
use Illuminate\Support\Facades\Crypt;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/home';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    public function getNameByPerson($idPersona){
        $persona = Persona::find($idPersona);
        return $persona->nombre;
    }

    public function getTypeById($idTipoUsuario){
        $tipoUsuario = TipoUsuario::find($idTipoUsuario);
        return $tipoUsuario->slug;
    }

    public function getAvatarByPerson($idPersona){
        $persona = Persona::find($idPersona);
        return $persona->fotografia;
    }

    public function login(Request $request){
        $usuario = Empleado::where('empleado.codigo', '=', $request['codigo'])
                            ->select('empleado.*')
                            ->first();
        if ($usuario) {
            $decryptPassword = Crypt::decryptString($usuario->password);
            if ($decryptPassword == $request['password']) {
                if ($usuario->id_tipo_usuario == $request['tipo']) {
                    if ($usuario->activo == 'Si') {
                        return response()->json([
                            'id' => $usuario->id,
                            'codigo' => $usuario->codigo,
                            'activo' => $usuario->activo,
                            'nombre' => $this->getNameByPerson($usuario->id_persona),
                            'tipo' => $this->getTypeById($usuario->id_tipo_usuario),
                            'avatar' => $this->getAvatarByPerson($usuario->id_persona),
                        ]);
                    } else {
                        return response('Usuario inactivo temporalmente', 400)->header('Content-Type', 'text/plain');
                    }
                }
            }
        }
        return response('Datos invalidos', 400)->header('Content-Type', 'text/plain');


        // $credentials = $request->only('codigo', 'password');
        // if (Auth::attempt($credentials)) {
        //     if (Auth::user()->id_tipo_usuario == $request['tipo']) {
        //         $usuario = Empleado::where('empleado.codigo', '=', $request['codigo'])
        //                     ->select('empleado.*')
        //                     ->first();
        //         if ($usuario->activo == 'Si') {
        //             return response()->json([
        //                 'id' => Auth::user()->id,
        //                 'codigo' => Auth::user()->codigo,
        //                 'activo' => Auth::user()->activo,
        //                 'nombre' => $this->getNameByPerson(Auth::user()->id_persona),
        //                 'tipo' => $this->getTypeById(Auth::user()->id_tipo_usuario),
        //                 'avatar' => $this->getAvatarByPerson(Auth::user()->id_persona),
        //             ]);
        //         } else {
        //             return response('Usuario inactivo temporalmente', 400)->header('Content-Type', 'text/plain');
        //         }
        //     }
        // }
        // return response('Datos invalidos', 400)->header('Content-Type', 'text/plain');
    }

    public function loginFingerPrint(Request $request)
{
    $host = "host.docker.internal"; 
    $port = 1234; 
    $message = "login"."\n";

     // Crear el socket
     $socket = socket_create(AF_INET, SOCK_STREAM, 0);
     if ($socket === false) {
         return response('No se pudo crear el socket', 500)->header('Content-Type', 'text/plain');
     }
 
     // Conectar al servidor
     $result = socket_connect($socket, $host, $port);
     if ($result === false) {
         return response('No se pudo conectar con el servidor', 500)->header('Content-Type', 'text/plain');
     }
 
     // Enviar el mensaje
     $result = socket_write($socket, $message, strlen($message));
     if ($result === false) {
         return response('No se pudo enviar datos al servidor', 500)->header('Content-Type', 'text/plain');
     }
 
     // Leer la respuesta
     $result = socket_read($socket, 1024);
     if ($result === false) {
         return response('No se pudo leer la respuesta del servidor', 500)->header('Content-Type', 'text/plain');
     }
 
     // Cerrar el socket
     socket_close($socket);
 
     // Procesar la respuesta
     $codigo = trim($result);  // Elimina espacios y saltos de línea
 
     // Buscar al empleado
     $usuario = Empleado::where('codigo', $codigo)->first();
     if ($usuario) {
         if ($usuario->activo == 'Si') {
             return response()->json([
                 'id' => $usuario->id,
                 'codigo' => $usuario->codigo,
                 'activo' => $usuario->activo,
                 'nombre' => $this->getNameByPerson($usuario->id_persona),
                 'tipo' => $this->getTypeById($usuario->id_tipo_usuario),
                 'avatar' => $this->getAvatarByPerson($usuario->id_persona),
             ]);
         } else {
             return response('Usuario inactivo temporalmente', 400)->header('Content-Type', 'text/plain');
         }
     } else {
         return response('Huella No Registrada', 400)->header('Content-Type', 'text/plain');
     }
 }

    







    // public function loginFingerPrint(Request $request){
    //     $usuario = Empleado::where('empleado.codigo', '=', $request['codigo'])
    //                     ->select('empleado.*')
    //                     ->first();
    //     if ($usuario) {
    //         if ($usuario->acceso_huella == true) {
    //             if ($usuario->activo == 'Si') {
    //                 return response()->json([
    //                     'id' => $usuario->id,
    //                     'codigo' => $usuario->codigo,
    //                     'activo' => $usuario->activo,
    //                     'nombre' => $this->getNameByPerson($usuario->id_persona),
    //                     'tipo' => $this->getTypeById($usuario->id_tipo_usuario),
    //                     'avatar' => $this->getAvatarByPerson($usuario->id_persona),
    //                 ]);
    //             } else {
    //                 return response('Usuario inactivo temporalmente', 400)->header('Content-Type', 'text/plain');
    //             }
    //         } else {
    //             return response('Acceso por huella denegado', 400)->header('Content-Type', 'text/plain');
    //         }
    //     } else {
    //         return response('Codigo invalido', 400)->header('Content-Type', 'text/plain');
    //     }
    // }
}
