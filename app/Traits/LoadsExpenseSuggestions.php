<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;

trait LoadsExpenseSuggestions
{
    public array $inChargeSuggestions     = [];
    public array $reasonSuggestions       = [];
    public array $detailSuggestions       = [];
    public array $documentTypeSuggestions = [];

    protected function loadExpenseSuggestions(): void
    {
        $this->inChargeSuggestions     = $this->distinctExpenseValues('in_charge');
        $this->reasonSuggestions       = $this->distinctExpenseValues('reason', 'Otros');
        $this->detailSuggestions       = $this->distinctExpenseValues('detail');
        $this->documentTypeSuggestions = $this->distinctExpenseValues('document_type');
    }

    /** Valores distintos ya usados en egresos, los más recientes/frecuentes primero */
    private function distinctExpenseValues(string $column, ?string $kind = null): array
    {
        $q = DB::table('expenses')
            ->whereNotNull($column)
            ->where($column, '!=', '');

        if ($kind !== null) {
            $q->where('kind', $kind);
        }

        return $q->select($column, DB::raw('MAX(date) as last_used'), DB::raw('COUNT(*) as uses'))
            ->groupBy($column)
            ->orderByDesc('last_used')
            ->orderByDesc('uses')
            ->limit(50)
            ->pluck($column)
            ->all();
    }
}
