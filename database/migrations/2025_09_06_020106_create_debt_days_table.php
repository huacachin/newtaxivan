<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('debt_days', function (Blueprint $table) {
            $table->id();

            // FK opcional a vehicles
            $table->foreignId('vehicle_id')->nullable()
                ->constrained('vehicles')->nullOnDelete()->cascadeOnUpdate();

            // Siempre guardamos la placa legacy para soportes y trazabilidad
            $table->string('legacy_plate', 20)->nullable()->index();

            // true si la placa NO existe en vehicles o si el vehículo está inactivo
            $table->boolean('is_support')->default(false)->index();

            // d1..d31 (valores: 'P', 'NT', 'X' o números como '1','2', etc)
            for ($i = 1; $i <= 31; $i++) {
                $table->string('d'.$i, 8)->nullable();
            }

            // Resúmenes
            $table->unsignedSmallInteger('days')->default(0);           // días con 'P'
            $table->decimal('total', 12, 2)->default(0);                 // suma de costos en días 'P'

            // Primer día del mes (YYYY-MM-01)
            $table->date('date')->index();

            // Exoneración
            $table->boolean('exonerated')->default(false);
            $table->string('detail_exonerated', 255)->nullable();

            // Amortización mensual (si aplica)
            $table->decimal('amortized', 12, 2)->default(0);

            // Condición snapshot (DT|GN|EX|EX5, etc)
            $table->string('condition', 10)->nullable()->index();

            // Días de deuda (celdas que no son 'P' ni 'NT' ni vacío)
            $table->unsignedSmallInteger('days_late')->default(0);

            $table->timestamps();

            // Evita duplicados por vehículo+mes (si no hay vehicle_id, se usa legacy_plate)
            $table->unique(['date', 'vehicle_id', 'legacy_plate'], 'uniq_debt_days_date_vehicle_plate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('debt_days');
    }
};
