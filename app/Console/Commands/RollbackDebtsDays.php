<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RollbackDebtsDays extends Command
{
    protected $signature = 'debt-days:rollback {--year=} {--month=}';
    protected $description = 'Restaura debt_days desde el respaldo generado antes de la última generación';

    public function handle(): int
    {
        $year  = (int) ($this->option('year') ?: now()->year);
        $month = (int) ($this->option('month') ?: now()->month);

        $file = "debt-days-backup/{$year}-" . str_pad((string)$month, 2, '0', STR_PAD_LEFT) . '.json';

        if (!Storage::exists($file)) {
            $this->error("No existe respaldo para {$year}-{$month}: {$file}");
            return self::FAILURE;
        }

        $backup = json_decode(Storage::get($file), true);

        if (empty($backup)) {
            $this->warn("El respaldo está vacío. Se borrará debt_days del mes.");
            DB::table('debt_days')
                ->whereYear('date', $year)
                ->whereMonth('date', $month)
                ->delete();
            return self::SUCCESS;
        }

        DB::transaction(function () use ($backup, $year, $month) {
            DB::table('debt_days')
                ->whereYear('date', $year)
                ->whereMonth('date', $month)
                ->delete();

            foreach (array_chunk($backup, 500) as $chunk) {
                DB::table('debt_days')->insert($chunk);
            }
        });

        $this->info("Restaurados " . count($backup) . " registros de debt_days para {$year}-{$month}.");

        return self::SUCCESS;
    }
}
