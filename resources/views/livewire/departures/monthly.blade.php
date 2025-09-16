<div class="container-fluid">
    <div class="text-center mb-2">
        @php
            $d = \Carbon\Carbon::create($year, $month, 1);
            $monthName = \Illuminate\Support\Str::upper($d->translatedFormat('F'));
        @endphp
        <h4 class="mb-2" style="color:#e11d48;">
            REPORTE MENSUAL POR PLACA – V.T {{ $monthName }} {{ $year }}
        </h4>

        <div class="d-inline-flex align-items-center gap-2">
            <label>Seleccione la fecha</label>
            <input type="date" class="form-control form-control-sm" style="max-width: 160px;"
                   wire:model.live="selectedDate">
            <button class="btn btn-light btn-sm" wire:click="prevMonth">◀</button>
            <button class="btn btn-light btn-sm" wire:click="nextMonth">▶</button>
            <button class="btn btn-primary btn-sm" wire:click="recalc">
                <i class="bi bi-search"></i> Consultar
            </button>
        </div>
    </div>

    <div class="mb-2" wire:loading.delay>
        <span class="text-muted"><span class="spinner-border spinner-border-sm"></span> Cargando…</span>
    </div>

    <style>
        th, td { white-space: nowrap; vertical-align: middle; text-align: center; }
        thead th.sticky { position: sticky; top: 0; background: #0ea5e9; color: #fff; z-index: 1; }
        .table thead th { background:#0ea5e9; color:#fff; }
        td.text-left { text-align: left; }
    </style>

    <div class="table-responsive">
        <table class="table table-sm table-bordered table-striped">
            <thead>
            <tr>
                <th class="sticky">Item</th>
                <th class="sticky">Placa</th>
                @foreach($days as $d)
                    @php
                        $w = \Carbon\Carbon::create($year, $month, $d)->dayOfWeekIso; // 7=Domingo
                        $isSun = ($w === 7);
                    @endphp
                    <th class="sticky" @if($isSun) style="background:#ef4444;color:#fff;" @endif>{{ $d }}</th>
                @endforeach
                <th class="sticky">T. Salida</th>
            </tr>
            </thead>

            <tbody>
            @php $i=0; $grandTotal=0; @endphp
            @foreach($rows as $row)
                @php $i++; $grandTotal += $row['total']; @endphp
                <tr>
                    <td>{{ $i }}</td>
                    <td class="text-left">{{ $row['plate'] }}</td>
                    @foreach($days as $d)
                        <td>{{ $row['daily'][$d] ?? 0 }}</td>
                    @endforeach
                    <td><strong>{{ $row['total'] }}</strong></td>
                </tr>
            @endforeach
            </tbody>

            <tfoot class="table-primary">
            <tr>
                <th colspan="2" class="text-left">Total Salidas</th>
                @foreach($days as $d)
                    <th>{{ $totalPerDay[$d] ?? 0 }}</th>
                @endforeach
                <th>{{ array_sum($totalPerDay) }}</th>
            </tr>
            <tr>
                <th colspan="2" class="text-left">Total V.T. (vehículos con salida)</th>
                @foreach($days as $d)
                    <th>{{ $vehiclesWorkedPerDay[$d] ?? 0 }}</th>
                @endforeach
                <th>{{ array_sum($vehiclesWorkedPerDay) }}</th>
            </tr>
            </tfoot>
        </table>
    </div>
</div>
