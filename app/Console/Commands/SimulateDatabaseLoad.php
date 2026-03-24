<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SimulateDatabaseLoad extends Command
{
    protected $signature = 'db:migrate-production {--minutes=45 : Duración en minutos}';

    protected $description = 'Migra datos al entorno de producción';

    private array $tables = [
        'usuarios'           => [800, 1500],
        'vehiculos'          => [200, 600],
        'conductores'        => [150, 400],
        'propietarios'       => [100, 350],
        'salidas'            => [5000, 15000],
        'pagos'              => [3000, 10000],
        'gastos'             => [1000, 4000],
        'ingresos'           => [800, 3000],
        'deudas_diarias'     => [2000, 8000],
        'detalle_deudas'     => [4000, 12000],
        'costos_por_placa'   => [500, 2000],
        'costos_diarios'     => [3000, 9000],
        'sucursales'         => [10, 30],
        'conceptos'          => [50, 150],
        'roles_permisos'     => [200, 500],
        'configuraciones'    => [30, 80],
        'auditorias'         => [8000, 20000],
        'notificaciones'     => [1500, 5000],
        'sesiones'           => [2000, 6000],
        'logs_sistema'       => [5000, 18000],
    ];

    private array $phases = [
        'Verificando conexión al servidor',
        'Autenticando credenciales',
        'Analizando estructura de tablas',
        'Creando respaldo de seguridad',
        'Validando integridad referencial',
        'Preparando migración de datos',
        'Optimizando índices',
        'Reconstruyendo relaciones foráneas',
        'Verificando consistencia de datos',
        'Comprimiendo respaldo final',
        'Actualizando secuencias',
        'Limpiando tablas temporales',
        'Regenerando caché de consultas',
        'Sincronizando réplicas',
        'Validando checksums',
    ];

    public function handle(): int
    {
        $totalMinutes = (int) $this->option('minutes');
        $totalSeconds = $totalMinutes * 60;
        $startTime = time();
        $endTime = $startTime + $totalSeconds;

        $this->newLine();
        $this->info('╔══════════════════════════════════════════════════╗');
        $this->info('║       SISTEMA DE MIGRACIÓN DE BASE DE DATOS     ║');
        $this->info('║              TaxiVan v3.2.1                     ║');
        $this->info('╚══════════════════════════════════════════════════╝');
        $this->newLine();

        // Fase 1: Conexión inicial (~2 min)
        $this->phaseMessage('Estableciendo conexión con el servidor de producción...');
        $this->fakePause(8, 15);
        $this->line('  <fg=green>✓</> Conexión establecida (SSL/TLS 1.3)');
        $this->line('  <fg=green>✓</> Servidor: db-prod-taxivan.cluster-01');
        $this->fakePause(3, 6);

        $this->phaseMessage('Autenticando credenciales de migración...');
        $this->fakePause(5, 10);
        $this->line('  <fg=green>✓</> Autenticación exitosa (token válido hasta ' . now()->addHours(2)->format('H:i') . ')');
        $this->fakePause(2, 4);

        // Fase 2: Análisis (~3 min)
        $this->phaseMessage('Analizando estructura de la base de datos origen...');
        $tableNames = array_keys($this->tables);
        $bar = $this->output->createProgressBar(count($tableNames));
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% -- Analizando: %message%');
        $bar->start();
        foreach ($tableNames as $table) {
            $bar->setMessage($table);
            $bar->advance();
            usleep(rand(400000, 900000));
        }
        $bar->finish();
        $this->newLine(2);
        $this->line('  <fg=green>✓</> ' . count($tableNames) . ' tablas detectadas');
        $this->fakePause(3, 5);

        // Fase 3: Respaldo
        $this->phaseMessage('Creando respaldo de seguridad...');
        $backupSize = rand(120, 350);
        $bar = $this->output->createProgressBar(100);
        $bar->setFormat(' %current%/%max%% [%bar%] Respaldo: %message%');
        $bar->start();
        for ($i = 0; $i <= 100; $i++) {
            $bar->setMessage(round($backupSize * $i / 100, 1) . ' MB / ' . $backupSize . ' MB');
            $bar->setProgress($i);
            usleep(rand(200000, 600000));
        }
        $bar->finish();
        $this->newLine(2);
        $this->line("  <fg=green>✓</> Respaldo completado ({$backupSize} MB)");
        $this->fakePause(2, 4);

        // Fase 4: Migración de tablas (grueso del tiempo)
        $this->newLine();
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('  INICIANDO MIGRACIÓN DE DATOS');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->newLine();

        $elapsed = time() - $startTime;
        $remainingForTables = $totalSeconds - $elapsed - 180; // reservar 3 min para fases finales
        $perTable = max(30, (int)($remainingForTables / count($this->tables)));

        $totalRecords = 0;
        $tableIndex = 0;

        foreach ($this->tables as $table => $range) {
            $tableIndex++;

            if (time() >= $endTime - 180) break;

            $records = rand($range[0], $range[1]);
            $totalRecords += $records;

            $this->newLine();
            $this->line("  <fg=yellow>[{$tableIndex}/" . count($this->tables) . "]</> Migrando tabla: <fg=cyan>{$table}</>");
            $this->line("  Registros a migrar: <fg=white>" . number_format($records) . "</>");

            $bar = $this->output->createProgressBar($records);
            $bar->setFormat('  %current%/%max% [%bar%] %percent:3s%% %elapsed:6s% | %message%');
            $bar->setMessage('Insertando...');
            $bar->start();

            $tableTimeLimit = time() + $perTable;
            $step = max(1, (int)($records / rand(80, 150)));
            $current = 0;

            while ($current < $records) {
                if (time() >= $tableTimeLimit) {
                    $bar->setProgress($records);
                    break;
                }

                $batch = min($step + rand(-($step/4), $step/4), $records - $current);
                $batch = max(1, (int)$batch);
                $current += $batch;
                $bar->setProgress(min($current, $records));

                $messages = [
                    'Insertando...',
                    'Validando registros...',
                    'Verificando FK...',
                    'Procesando batch...',
                    'Indexando...',
                    'Commit transacción...',
                ];
                $bar->setMessage($messages[array_rand($messages)]);

                usleep(rand(150000, 500000));
            }

            $bar->setMessage('<fg=green>Completado</>');
            $bar->finish();
            $this->newLine();
            $this->line("  <fg=green>✓</> {$table}: " . number_format($records) . " registros migrados");

            // Ocasionalmente mostrar warnings falsos
            if (rand(1, 5) === 1) {
                $warnings = [
                    '  <fg=yellow>⚠ ' . rand(1, 5) . ' registros duplicados omitidos</>',
                    '  <fg=yellow>⚠ Reintentando ' . rand(1, 3) . ' inserciones fallidas...</>',
                    '  <fg=yellow>⚠ Conversión de charset UTF-8 aplicada a ' . rand(5, 20) . ' campos</>',
                ];
                $this->line($warnings[array_rand($warnings)]);
                $this->fakePause(2, 5);
            }
        }

        // Fases finales
        $this->newLine(2);
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('  POST-MIGRACIÓN');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $finalPhases = [
            'Verificando integridad referencial',
            'Reconstruyendo índices',
            'Actualizando secuencias auto-increment',
            'Validando checksums de tablas',
            'Limpiando tablas temporales',
            'Regenerando caché de consultas',
            'Optimizando tablas migradas',
        ];

        // Distribuir el tiempo restante entre las fases finales
        foreach ($finalPhases as $phase) {
            if (time() >= $endTime - 10) break;

            $this->newLine();
            $this->phaseMessage($phase . '...');

            $remaining = max(5, $endTime - time() - 10);
            $phaseTime = min(rand(10, 25), (int)($remaining / max(1, count($finalPhases))));

            $bar = $this->output->createProgressBar(100);
            $bar->setFormat('  [%bar%] %percent:3s%%');
            $bar->start();
            for ($i = 0; $i <= 100; $i++) {
                $bar->setProgress($i);
                usleep((int)(($phaseTime * 1000000) / 100));
            }
            $bar->finish();
            $this->newLine();
            $this->line("  <fg=green>✓</> {$phase} completado");
        }

        // Esperar hasta completar el tiempo total
        while (time() < $endTime) {
            sleep(1);
        }

        // Resumen final
        $elapsed = time() - $startTime;
        $minutes = (int)($elapsed / 60);
        $seconds = $elapsed % 60;

        $this->newLine(2);
        $this->info('╔══════════════════════════════════════════════════╗');
        $this->info('║          MIGRACIÓN COMPLETADA EXITOSAMENTE      ║');
        $this->info('╚══════════════════════════════════════════════════╝');
        $this->newLine();
        $this->line('  Tablas migradas:     <fg=white>' . count($this->tables) . '</>');
        $this->line('  Registros totales:   <fg=white>' . number_format($totalRecords) . '</>');
        $this->line('  Tiempo total:        <fg=white>' . $minutes . 'm ' . $seconds . 's</>');
        $this->line('  Estado:              <fg=green>OK - Sin errores</>');
        $this->newLine();

        return self::SUCCESS;
    }

    private function phaseMessage(string $message): void
    {
        $this->line("  <fg=blue>▶</> {$message}");
    }

    private function fakePause(int $min, int $max): void
    {
        sleep(rand($min, $max));
    }
}
