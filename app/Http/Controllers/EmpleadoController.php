<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\EmpleadoExport;

use App\Models\Empleado;
use App\Models\TipoUsuario;
use App\Models\Ciudad;
use App\Models\Departamento;
use App\Models\Persona;
use App\Models\Profesion;
use App\Models\TipoSangre;
use App\Models\IdiomaEmpleado;
use App\Models\EmpleadoLicencia;
use App\Models\CategoriaLicencia;
use App\Models\EmpleadoLibreta;
use App\Models\EmpleadoCursoBomberil;
use App\Models\EmpleadoEducacion;
use App\Models\EmpleadoExperiencia;
use App\Models\EmpleadoInformacionBomberil;
use App\Models\EmpleadoCapacitacion;
use App\Models\EmpleadoAscenso;
use App\Models\EmpleadoCondecoracion;
use App\Models\EmpleadoNombramiento;
use App\Models\EmpleadoSancion;
use App\Models\Cargo;
use App\Models\Huella;
// use DB;
// EN OTRO METODO ESPECIAL:
// Traer a los demas modelos que hace parte del empleado(emplado_licencia, empleado... etc)
// Pues debemos agregar esa informacion a las tablas correspondientes
// Si ya tienes controlador, podemos llamar sus metodos, para no extender mas este.
// Log, EmpleadoLicencia, EmpleadoLibreta, EmpleadoExperiencia, EmpleadoEducacion

class EmpleadoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $empleados = Empleado::all();

        // Cargar todas las relaciones en una consulta para reducir el número de accesos a la base de datos
        $nombramientosActivos = EmpleadoNombramiento::where('activo', '=', 'Si')
            ->orderBy('id', 'DESC')
            ->get()
            ->keyBy('id_empleado');
        $cargos = Cargo::all()->keyBy('id');
        $tiposUsuario = TipoUsuario::all()->keyBy('id');
        $ciudades = Ciudad::all()->keyBy('id');
        $departamentos = Departamento::all()->keyBy('id');
        $personas = Persona::all()->keyBy('id');

        foreach ($empleados as $empleado) {
            // Optimización para obtener el cargo del nombramiento activo
            if (isset($nombramientosActivos[$empleado->id])) {
                $nombramiento = $nombramientosActivos[$empleado->id];
                $empleado->cargo = $cargos->get($nombramiento->id_cargo) ?? null;
            } else {
                $empleado->cargo = null;
            }

            // Asignar el tipo de usuario
            $empleado->tipo_usuario = $tiposUsuario->get($empleado->id_tipo_usuario) ?? null;

            // Asignar ciudad y departamento
            $ciudad = $ciudades->get($empleado->id_ciudad);
            if ($ciudad) {
                $ciudad->departamento = $departamentos->get($ciudad->id_departamento) ?? null;
                $empleado->ciudad = $ciudad;
            }

            // Asignar la persona
            $empleado->persona = $personas->get($empleado->id_persona) ?? null;

            // Eliminar IDs para evitar redundancia
            unset($empleado->id_tipo_usuario);
            unset($empleado->id_ciudad);
            unset($empleado->id_persona);
        }

        return $empleados;
    }



    public function curriculums()
    {
        // Primero obtenemos todos los empleados con el método optimizado de `index`.
        $empleados = $this->index();

        // Preconsultas de relaciones en lotes para evitar múltiples consultas dentro del bucle
        $cursos = EmpleadoCursoBomberil::all()->groupBy('id_empleado');
        $educaciones = EmpleadoEducacion::join('ciudad', 'empleado_educacion.id_ciudad', '=', 'ciudad.id')
            ->join('departamento', 'ciudad.id_departamento', '=', 'departamento.id')
            ->select('empleado_educacion.*', 'ciudad.nombre as ciudad', 'departamento.nombre as departamento')
            ->get()->groupBy('id_empleado');
        $experiencias = EmpleadoExperiencia::all()->groupBy('id_empleado');
        $informaciones = EmpleadoInformacionBomberil::join('rango', 'empleado_informacion_bomberil.id_rango', '=', 'rango.id')
            ->select('empleado_informacion_bomberil.*', 'rango.nombre as rango')
            ->get()->groupBy('id_empleado');
        $capacitaciones = EmpleadoCapacitacion::all()->groupBy('id_empleado');
        $ascensos = EmpleadoAscenso::join('rango', 'empleado_ascenso.id_rango', '=', 'rango.id')
            ->select('empleado_ascenso.*', 'rango.nombre as rango')
            ->get()->groupBy('id_empleado');
        $condecoraciones = EmpleadoCondecoracion::join('tipo_condecoracion', 'empleado_condecoracion.id_tipo_condecoracion', '=', 'tipo_condecoracion.id')
            ->select('empleado_condecoracion.*', 'tipo_condecoracion.nombre as tipo_condecoracion')
            ->get()->groupBy('id_empleado');
        $nombramientos = EmpleadoNombramiento::join('cargo', 'empleado_nombramiento.id_cargo', '=', 'cargo.id')
            ->select('empleado_nombramiento.*', 'cargo.nombre as cargo')
            ->get()->groupBy('id_empleado');
        $sanciones = EmpleadoSancion::all()->groupBy('id_empleado');

        // Asignar las relaciones a cada empleado
        foreach ($empleados as $empleado) {
            $id = $empleado->id;
            $empleado->cursos = $cursos->get($id) ?? [];
            $empleado->educaciones = $educaciones->get($id) ?? [];
            $empleado->experiencias = $experiencias->get($id) ?? [];
            $empleado->informaciones = $informaciones->get($id) ?? [];
            $empleado->capacitaciones = $capacitaciones->get($id) ?? [];
            $empleado->ascensos = $ascensos->get($id) ?? [];
            $empleado->condecoraciones = $condecoraciones->get($id) ?? [];
            $empleado->nombramientos = $nombramientos->get($id) ?? [];
            $empleado->sanciones = $sanciones->get($id) ?? [];
        }

        return $empleados;
    }


    public function curriculumPersonal($id)
    {
        $empleado = Empleado::with([
            'cursos' => function ($query) {
                $query->select('*');
            },
            'educaciones' => function ($query) {
                $query->join('ciudad', 'empleado_educacion.id_ciudad', '=', 'ciudad.id')
                    ->join('departamento', 'ciudad.id_departamento', '=', 'departamento.id')
                    ->select('empleado_educacion.*', 'ciudad.nombre as ciudad', 'departamento.nombre as departamento');
            },
            'experiencias' => function ($query) {
                $query->select('*');
            },
            'informaciones' => function ($query) {
                $query->join('rango', 'empleado_informacion_bomberil.id_rango', '=', 'rango.id')
                    ->select('empleado_informacion_bomberil.*', 'rango.nombre as rango');
            },
            'capacitaciones' => function ($query) {
                $query->select('*');
            },
            'ascensos' => function ($query) {
                $query->join('rango', 'empleado_ascenso.id_rango', '=', 'rango.id')
                    ->select('empleado_ascenso.*', 'rango.nombre as rango');
            },
            'condecoraciones' => function ($query) {
                $query->join('tipo_condecoracion', 'empleado_condecoracion.id_tipo_condecoracion', '=', 'tipo_condecoracion.id')
                    ->select('empleado_condecoracion.*', 'tipo_condecoracion.nombre as tipo_condecoracion');
            },
            'nombramientos' => function ($query) {
                $query->join('cargo', 'empleado_nombramiento.id_cargo', '=', 'cargo.id')
                    ->select('empleado_nombramiento.*', 'cargo.nombre as cargo');
            },
            'sanciones' => function ($query) {
                $query->select('*');
            }
        ])->find($id);

        if ($empleado) {
            return $empleado;
        }

        return response('Registro no encontrado', 400)->header('Content-Type', 'text/plain');
    }


    public function inspectores()
    {
        $empleados = Empleado::join('tipo_usuario', 'empleado.id_tipo_usuario', '=', 'tipo_usuario.id')
            ->where('tipo_usuario.slug', '=', 'supervisor')
            ->select('empleado.*')
            ->get();
        foreach ($empleados as $empleado) {
            $nombramiento = EmpleadoNombramiento::where('empleado_nombramiento.id_empleado', '=', $empleado->id)
                ->where('empleado_nombramiento.activo', '=', 'Si')
                ->orderBy('id', 'DESC')
                ->first();
            if ($nombramiento) {
                $cargo = Cargo::find($nombramiento->id_cargo);
                $empleado->cargo = $cargo;
            } else {
                $empleado->cargo = null;
            }
            $tipoUsuario = TipoUsuario::find($empleado->id_tipo_usuario);
            $ciudad = Ciudad::find($empleado->id_ciudad);
            $departamento = Departamento::find($ciudad->id_departamento);
            $persona = Persona::find($empleado->id_persona);
            $empleado->tipo_usuario = $tipoUsuario;
            $ciudad->departamento = $departamento;
            $empleado->ciudad = $ciudad;
            $empleado->persona = $persona;
            unset($empleado->id_tipo_usuario);
            unset($empleado->id_ciudad);
            unset($ciudad->id_departamento);
            unset($empleado->id_persona);
        }
        return $empleados;
    }

    public function employeeWithoutAppointment()
    {
        $empleados = Empleado::leftJoin('empleado_nombramiento', 'empleado.id', '=', 'empleado_nombramiento.id_empleado')
            ->where('empleado_nombramiento.id_empleado', '=', null)
            ->select('empleado.*')
            ->get();
        foreach ($empleados as $empleado) {
            $nombramiento = EmpleadoNombramiento::where('empleado_nombramiento.id_empleado', '=', $empleado->id)
                ->where('empleado_nombramiento.activo', '=', 'Si')
                ->orderBy('id', 'DESC')
                ->first();
            if ($nombramiento) {
                $cargo = Cargo::find($nombramiento->id_cargo);
                $empleado->cargo = $cargo;
            } else {
                $empleado->cargo = null;
            }
            $tipoUsuario = TipoUsuario::find($empleado->id_tipo_usuario);
            $ciudad = Ciudad::find($empleado->id_ciudad);
            $departamento = Departamento::find($ciudad->id_departamento);
            $persona = Persona::find($empleado->id_persona);
            $empleado->tipo_usuario = $tipoUsuario;
            $ciudad->departamento = $departamento;
            $empleado->ciudad = $ciudad;
            $empleado->persona = $persona;
            unset($empleado->id_tipo_usuario);
            unset($empleado->id_ciudad);
            unset($ciudad->id_departamento);
            unset($empleado->id_persona);
        }
        return $empleados;
    }

    public function empleadosSinHuellas()
    {
        $empleados = Empleado::leftJoin('huella', 'empleado.id', '=', 'huella.id_empleado')
            ->where('huella.id_empleado', '=', null)
            ->select('empleado.*')
            ->get();
        foreach ($empleados as $empleado) {
            $persona = Persona::find($empleado->id_persona);
            $empleado->persona = $persona;
            unset($empleado->id_persona);
        }
        return $empleados;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // Validación inicial
        if (!$request->filled(['idTipoUsuario', 'idCiudad', 'idPersona', 'codigo', 'password'])) {
            return response('Datos faltantes', 400)->header('Content-Type', 'text/plain');
        }

        // Encuentra las entidades necesarias para validar antes de crear
        $idTipoUsuario = TipoUsuario::find($request['idTipoUsuario']);
        $idCiudad = Ciudad::find($request['idCiudad']);
        $idPersona = Persona::find($request['idPersona']);
        if (!$idTipoUsuario || !$idCiudad || !$idPersona) {
            return response('Datos faltantes', 400)->header('Content-Type', 'text/plain');
        }

        // Creación del empleado
        $empleado = Empleado::create([
            'codigo' => $request['codigo'],
            'codigo_sistema_nacional_npib' => $request->input('codigoSistemaNacionalNpib', null),
            'password' => Crypt::encryptString($request['password']),
            'fecha_ingreso' => $request->input('fechaIngreso', now()),
            'activo' => $request->input('activo', 'Si'),
            'radicacion' => $request->input('radicacion', null),
            'pasaporte' => $request->input('pasaporte', null),
            'seguro' => $request->input('seguro', null),
            'tipo_casa' => $request->input('tipoCasa', null),
            'personas_a_cargo' => $request->input('personasACargo', 0),
            'actividad' => $request->input('actividad', null),
            'labor' => $request->input('labor', null),
            'maquina' => $request->input('maquina', null),
            'computador' => $request->input('computador', null),
            'hobi' => $request->input('hobi', null),
            'id_tipo_usuario' => $request['idTipoUsuario'],
            'id_ciudad' => $request['idCiudad'],
            'id_persona' => $request['idPersona']
        ]);

        // Almacena los idiomas asociados al empleado
        if ($request->filled('idiomas')) {
            $idiomasData = collect($request['idiomas'])->map(function ($idioma) use ($empleado) {
                return [
                    'id_idioma' => $idioma['id'],
                    'id_empleado' => $empleado->id
                ];
            })->toArray();

            IdiomaEmpleado::insert($idiomasData);
        }


        // Almacena la licencia si está presente
        if ($request->filled('licencia')) {
            $licencia = $request['licencia'];
            EmpleadoLicencia::create([
                'fecha_expedicion' => $licencia['fechaExpedicion'],
                'fecha_vigencia' => $licencia['fechaVigencia'],
                'id_categoria_licencia' => $licencia['categoria']['id'],
                'id_empleado' => $empleado->id
            ]);
        }

        // Almacena la libreta si está presente
        if ($request->filled('libreta')) {
            $libreta = $request['libreta'];
            EmpleadoLibreta::create([
                'clase' => $libreta['clase'],
                'distrito' => $libreta['distrito'],
                'id_empleado' => $empleado->id
            ]);
        }

        return $empleado;
    }



    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $empleado = Empleado::find($id);

        if (!$empleado) {
            return response('Registro no encontrado', 400)->header('Content-Type', 'text/plain');
        }

        // Obtener los idiomas del empleado
        $idiomas = IdiomaEmpleado::join('idioma', 'idioma_empleado.id_idioma', '=', 'idioma.id')
            ->where('idioma_empleado.id_empleado', $id)
            ->select('idioma.*')
            ->get();

        // Obtener libreta y licencia del empleado, junto con sus detalles de categoría si existen
        $libreta = EmpleadoLibreta::where('empleado_libreta.id_empleado', $id)->first();
        $licencia = EmpleadoLicencia::with('categoriaLicencia')->where('empleado_licencia.id_empleado', $id)->first();

        // Nombramiento y cargo
        $nombramiento = EmpleadoNombramiento::where('id_empleado', $empleado->id)
            ->where('activo', 'Si')
            ->latest('id')
            ->first();
        $empleado->cargo = $nombramiento ? Cargo::find($nombramiento->id_cargo) : null;

        // Relaciones tipoUsuario, ciudad y persona
        $tipoUsuario = TipoUsuario::find($empleado->id_tipo_usuario);
        $ciudad = Ciudad::find($empleado->id_ciudad);
        $departamento = $ciudad ? Departamento::find($ciudad->id_departamento) : null;
        $persona = Persona::find($empleado->id_persona);

        if ($persona) {
            $persona->profesion = Profesion::find($persona->id_profesion);
            $persona->tipo_sangre = TipoSangre::find($persona->id_tipo_sangre);

            // Ciudad y departamento de nacimiento
            $ciudadNacimiento = Ciudad::find($persona->id_ciudad_nacimiento);
            $ciudadNacimiento->departamento = $ciudadNacimiento ? Departamento::find($ciudadNacimiento->id_departamento) : null;
            $persona->ciudad_nacimiento = $ciudadNacimiento;
        }

        // Asignación de datos finales
        $empleado->tipo_usuario = $tipoUsuario;
        if ($ciudad) {
            $ciudad->departamento = $departamento;
            $empleado->ciudad = $ciudad;
        }
        $empleado->persona = $persona;
        $empleado->password = Crypt::decryptString($empleado->password);
        $empleado->idiomas = $idiomas;
        $empleado->libreta = $libreta;
        $empleado->licencia = $licencia;

        // Remover atributos innecesarios
        unset($empleado->id_tipo_usuario, $empleado->id_ciudad, $empleado->id_persona);

        return $empleado;
    }


    public function showHasFingerPrint($id)
    {
        $empleado = Empleado::find($id);
        $conHuella = Huella::where('huella.id_empleado', '=', $id)->get();
        return $conHuella ? $conHuella : [];
    }

    public function EmployeeValidForEdit($id)
    {
        $empleado = Empleado::find($id);
        if ($empleado->id_tipo_usuario == 1 || $empleado->id_tipo_usuario == 6) {
            return $empleado;
        }
        return [];
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
        $empleado = Empleado::find($id);

        if (!$empleado) {
            return response('Empleado no encontrado', 400)->header('Content-Type', 'text/plain');
        }

        // Actualización de licencia
        if ($request->has('licencia')) {
            $licenciaData = $request->input('licencia');
            $licencia = EmpleadoLicencia::updateOrCreate(
                ['id_empleado' => $id],
                [
                    'fecha_expedicion' => $licenciaData['fechaExpedicion'] ?? null,
                    'fecha_vigencia' => $licenciaData['fechaVigencia'] ?? null,
                    'id_categoria_licencia' => $licenciaData['categoria']['id'] ?? null
                ]
            );
        } else {
            EmpleadoLicencia::where('id_empleado', $id)->delete();
        }

        // Actualización de libreta
        if ($request->has('libreta')) {
            $libretaData = $request->input('libreta');
            EmpleadoLibreta::updateOrCreate(
                ['id_empleado' => $id],
                [
                    'clase' => $libretaData['clase'] ?? null,
                    'distrito' => $libretaData['distrito'] ?? null
                ]
            );
        } else {
            EmpleadoLibreta::where('id_empleado', $id)->delete();
        }

        // Actualización de idiomas
        // Actualización de idiomas
        $idiomas = $request->input('idiomas', []);
        IdiomaEmpleado::where('id_empleado', $id)->delete();

        $idiomasData = collect($idiomas)->map(function ($idioma) use ($id) {
            return [
                'id_idioma' => $idioma['id'],
                'id_empleado' => $id
            ];
        })->toArray();

        IdiomaEmpleado::insert($idiomasData);


        // Validación de relaciones antes de asignar
        $validTipoUsuario = TipoUsuario::find($request->input('idTipoUsuario'));
        $validCiudad = Ciudad::find($request->input('idCiudad'));
        $validPersona = Persona::find($request->input('idPersona'));

        if ($validTipoUsuario && $validCiudad && $validPersona) {
            // Actualización de datos de empleado
            $empleado->update([
                'codigo' => $request->input('codigo', $empleado->codigo),
                'codigo_sistema_nacional_npib' => $request->input('codigoSistemaNacionalNpib', $empleado->codigo_sistema_nacional_npib),
                'password' => $request->filled('password') ? Crypt::encryptString($request->input('password')) : $empleado->password,
                'fecha_ingreso' => $request->input('fechaIngreso', $empleado->fecha_ingreso),
                'activo' => $request->input('activo', $empleado->activo),
                'radicacion' => $request->input('radicacion', $empleado->radicacion),
                'pasaporte' => $request->input('pasaporte', $empleado->pasaporte),
                'seguro' => $request->input('seguro', $empleado->seguro),
                'tipo_casa' => $request->input('tipoCasa', $empleado->tipo_casa),
                'personas_a_cargo' => $request->input('personasACargo', $empleado->personas_a_cargo),
                'actividad' => $request->input('actividad', $empleado->actividad),
                'labor' => $request->input('labor', $empleado->labor),
                'maquina' => $request->input('maquina', $empleado->maquina),
                'computador' => $request->input('computador', $empleado->computador),
                'hobi' => $request->input('hobi', $empleado->hobi),
                'id_tipo_usuario' => $request->input('idTipoUsuario', $empleado->id_tipo_usuario),
                'id_ciudad' => $request->input('idCiudad', $empleado->id_ciudad),
                'id_persona' => $request->input('idPersona', $empleado->id_persona)
            ]);
        } else {
            return response('Datos inválidos para actualizar el empleado', 400)->header('Content-Type', 'text/plain');
        }

        return $empleado;
    }


    public function updatePermissions(Request $request, $id)
    {
        $empleado = Empleado::find($id);
        if ($empleado) {
            $empleado->acceso_huella = isset($request['accesoHuella']) ? $request['accesoHuella'] : $empleado->acceso_huella;
            $empleado->save();
            return $empleado;
        }
        return [];
    }

    public function cancelFingerPrint()
    {
        $empleados = Empleado::all();
        foreach ($empleados as $empleado) {
            $empleado->acceso_huella = 0;
            $empleado->save();
        }
        return $empleados;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $empleado = Empleado::find($id);
        if ($empleado) {
            $empleado->delete();
            return $empleado;
        }
        return [];
    }

    public function excelReport()
    {
        return new EmpleadoExport();
    }
}
