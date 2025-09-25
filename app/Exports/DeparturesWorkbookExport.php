<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class DeparturesWorkbookExport implements WithMultipleSheets
{
    public function __construct(
        protected ?int $searchType = 1,
        protected ?string $searchText = null,
        protected ?string $fromDate = null,
        protected ?string $toDate = null,
        protected bool $groupMode = false
    ) {}

    public function sheets(): array
    {
        return [
            new DeparturesMainExport($this->searchType, $this->searchText, $this->fromDate, $this->toDate, $this->groupMode),
            new DeparturesSupportExport($this->searchType, $this->searchText, $this->fromDate, $this->toDate, $this->groupMode),
        ];
    }
}
