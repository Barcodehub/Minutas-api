<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTipoEmergencyToEmergencyTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('emergency', function (Blueprint $table) {
            $table->string('tipoEmergency')->nullable(); // Ajusta el tipo de datos según sea necesario
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('emergency', function (Blueprint $table) {
            $table->dropColumn('tipoEmergency');
        });
    }
}
