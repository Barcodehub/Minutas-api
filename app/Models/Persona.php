<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class Persona extends Model implements Auditable
{
    // use SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $table = 'persona';
    protected $hidden = ['pivot'];

    protected $fillable = [
        'nombre', 'fecha_nacimiento', 'sexo', 'vive', 'barrio', 'direccion', 'telefono',
        'celular', 'estado_civil', 'documento', 'estatura', 'peso', 'email', 'fotografia',
        'id_tipo_sangre', 'id_ciudad_nacimiento', 'id_profesion', 
    ];

    // Uno a uno con empleado
    public function empleado()
    {
        return $this->hasOne('App\Models\Empleado', 'id_persona');
    }

    // belongsTo de tipo_sangre
    public function tipoSangre()
    {
        return $this->belongsTo('App\Models\TipoSangre', 'id_tipo_sangre');
    }

    // belongsTo de ciudad (id_ciudad_nacimiento)
    public function ciudadNacimiento()
    {
        return $this->belongsTo('App\Models\Ciudad', 'id_ciudad_nacimiento');
    }

    // belongsTo de profesion
    public function profesion()
    {
        return $this->belongsTo('App\Models\Profesion', 'id_profesion');
    }
}
