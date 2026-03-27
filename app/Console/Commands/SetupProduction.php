<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SetupProduction extends Command
{
    protected $signature = 'taxivan:setup-production
        {--skip-legacy : Solo ejecutar seeders, sin migración de tablas legacy}
        {--chunk=1000 : Tamaño del chunk para migraciones legacy}';

    protected $description = 'Ejecuta seeders + migraciones legacy + fixes post-migración en un solo comando';

    public function handle(): int
    {
        $skipLegacy = $this->option('skip-legacy');
        $chunk      = (int) $this->option('chunk');

        $this->info('');
        $this->info('╔══════════════════════════════════════════╗');
        $this->info('║     TAXIVAN — SETUP PRODUCCIÓN           ║');
        $this->info('╚══════════════════════════════════════════╝');
        $this->info('');

        // ─── FASE 0: Seeders base ───────────────────────────────────
        $this->phase('FASE 0', 'Seeders base');

        $this->step('Permisos');
        $this->call('db:seed', ['--class' => 'PermissionCatalogSeeder', '--force' => true]);

        $this->step('Roles');
        $this->call('db:seed', ['--class' => 'RoleSetupSeeder', '--force' => true]);

        $this->step('Sucursales');
        $this->call('db:seed', ['--class' => 'HeadquartersSeeder', '--force' => true]);

        $this->step('Conceptos');
        $this->call('db:seed', ['--class' => 'ConceptsSeeder', '--force' => true]);

        $this->step('Usuarios (roles nuevos)');
        $this->call('db:seed', ['--class' => 'UsersSeeder', '--force' => true]);

        if ($skipLegacy) {
            $this->info('');
            $this->info('✓ Setup completado (sin migración legacy).');
            return self::SUCCESS;
        }

        // ─── FASE 1: Maestros ───────────────────────────────────────
        $this->phase('FASE 1', 'Maestros (legacy → nueva BD)');

        $this->step('Conductores');
        $this->call('taxivan:migrate-drivers', ['--chunk' => $chunk]);

        $this->step('Propietarios');
        $this->call('taxivan:migrate-owners', ['--chunk' => $chunk]);

        // Fix: owners migrados deben quedar como inactive
        $this->step('Fix: owners → status inactive');
        $affected = DB::table('owners')->whereIn('status', ['active', 'activo'])->update(['status' => 'inactive']);
        $this->line("  → {$affected} propietarios actualizados a inactive.");

        $this->step('Vehículos (depende de conductores y propietarios)');
        $this->call('taxivan:migrate-vehicles', ['--chunk' => $chunk]);

        // ─── FASE 2: Transacciones ──────────────────────────────────
        $this->phase('FASE 2', 'Transacciones');

        $this->step('Salidas');
        $this->call('taxivan:migrate-departures', ['--chunk' => $chunk]);

        $this->step('Pagos');
        $this->call('taxivan:migrate-payments', ['--chunk' => $chunk]);

        // ─── FASE 3: Financieros ────────────────────────────────────
        $this->phase('FASE 3', 'Financieros');

        $this->step('Egresos');
        $this->call('taxivan:migrate-expenses', ['--chunk' => $chunk]);

        $this->step('Ingresos');
        $this->call('taxivan:migrate-incomes', ['--chunk' => $chunk]);

        // Fix: expenses con reason=DRACO y detail=Sta Anita → headquarter_id=3
        $this->step('Fix: expenses DRACO/Sta Anita → headquarter_id=3');
        $affected = DB::table('expenses')
            ->where('reason', 'DRACO')
            ->where('detail', 'Sta Anita')
            ->update(['headquarter_id' => 3]);
        $this->line("  → {$affected} egresos actualizados con headquarter_id=3.");

        // ─── FASE 4: Costos ─────────────────────────────────────────
        $this->phase('FASE 4', 'Costos por placa');

        $this->step('Costo por placa (mensual)');
        $this->call('migrate:costpla', ['--chunk' => $chunk]);

        $this->step('Costo por placa (diario)');
        $this->call('migrate:costpla-day', ['--chunk' => $chunk]);

        // ─── FASE 5: Deudas ─────────────────────────────────────────
        $this->phase('FASE 5', 'Deudas');

        $this->step('Deuda por días');
        $this->call('taxivan:migrate-debt-days', ['--chunk' => $chunk]);

        $this->step('Detalle de deuda por días');
        $this->call('taxivan:migrate-debt-days-detail', ['--chunk' => $chunk]);

        // ─── Fin ────────────────────────────────────────────────────
        $this->info('');
        $this->info('╔══════════════════════════════════════════╗');
        $this->info('║     ✓ SETUP PRODUCCIÓN COMPLETADO        ║');
        $this->info('╚══════════════════════════════════════════╝');
        $this->info('');

        return self::SUCCESS;
    }

    private function phase(string $tag, string $title): void
    {
        $this->info('');
        $this->info("── {$tag}: {$title} ──────────────────────────");
    }

    private function step(string $name): void
    {
        $this->comment("  ▸ {$name}");
    }
}
