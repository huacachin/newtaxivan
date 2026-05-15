<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Renombra las columnas relacionadas a la antigua "Constancia de Educación Vial"
 * al nuevo nombre de negocio "Cartilla Informativa" en la tabla drivers.
 *
 * Operación metadata-only en MySQL 8: la data existente se conserva, solo
 * cambian los nombres de las 3 columnas (date, date, varchar).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->renameColumn('road_education',                  'cartilla_informativa');
            $table->renameColumn('road_education_expiration_date',  'cartilla_informativa_expiration_date');
            $table->renameColumn('road_education_municipality',     'cartilla_informativa_municipality');
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->renameColumn('cartilla_informativa',                  'road_education');
            $table->renameColumn('cartilla_informativa_expiration_date',  'road_education_expiration_date');
            $table->renameColumn('cartilla_informativa_municipality',     'road_education_municipality');
        });
    }
};
