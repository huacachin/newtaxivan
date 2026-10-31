{{-- PDF del orden de salida de un día (sorteo real o simulado), para descargar y
     mandar por WhatsApp como archivo. dompdf no entiende flex ni grid: todo va en
     tablas. Logo "Taxivan" (imagen en Brush Script, como el de Huacachin) con su franja roja,
     franja roja bajo la fecha y la lista en 3 columnas con los 3 primeros en rojo. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 0; }
        body { margin: 0; font-family: Helvetica, Arial, sans-serif; color: #0f172a; font-size: 11px; }
        table { border-collapse: collapse; }
        .accent td { height: 7px; padding: 0; }
        .page { padding: 26px 36px 18px; }
        .top { width: 100%; }
        .top td { vertical-align: middle; padding: 0; }
        .brand { display: block; height: 36px; }
        .brand-stripe { display: block; width: 136px; height: 5px; margin-top: 2px; }
        .tag { font-size: 9px; font-weight: bold; letter-spacing: 1px; padding: 4px 10px; border-radius: 9px; }
        .tag-sim { color: #ff4a12; background: #ffece4; }
        .tag-annulled { color: #b91c1c; background: #fee2e2; }
        .eyebrow { margin: 20px 0 3px; font-size: 9px; font-weight: bold; letter-spacing: 2px; color: #0b22a8; text-transform: uppercase; }
        .title { margin: 0; font-size: 26px; font-weight: bold; }
        .stripe { display: block; width: 220px; height: 6px; margin: 7px 0 12px; }
        .chips td { padding: 0 6px 0 0; }
        .chip { background: #f1f4f9; color: #64748b; border-radius: 9px; padding: 4px 10px; font-size: 10px; }
        .chip b { color: #0f172a; }
        .annulled { margin-top: 12px; padding: 8px 10px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 6px; color: #991b1b; font-size: 10px; }
        .list { width: 100%; margin-top: 14px; border-collapse: separate; border-spacing: 0 6px; }
        .list tr { page-break-inside: avoid; }
        .item { width: 33.33%; padding: 0; vertical-align: top; }
        .card { border: 1px solid #e6eaf0; border-radius: 8px; padding: 6px 8px; }
        .gap { width: 7px; padding: 0; }
        .card.top3 { border-color: #ffc9b5; background: #fff8f5; }
        .inner { width: 100%; }
        .inner td { padding: 0; vertical-align: middle; }
        .num-cell { width: 30px; }
        .num { width: 24px; padding: 6px 0; line-height: 12px; text-align: center; border-radius: 6px; font-weight: bold; font-size: 11px;
            color: #0b22a8; background: #edf0fb; }
        .top3 .num { color: #ffffff; background: #ff4a12; }
        .plate { font-weight: bold; font-size: 11.5px; letter-spacing: .5px; }
        .driver { margin-top: 1px; color: #64748b; font-size: 8.5px; line-height: 1.2; }
        .annulled-list .plate, .annulled-list .driver { color: #94a3b8; }
        .foot { width: 100%; margin-top: 12px; border-top: 1px solid #e6eaf0; }
        .foot td { padding-top: 10px; vertical-align: middle; }
        .muted { color: #64748b; font-size: 9px; }
    </style>
</head>
<body>
<table style="width: 100%;" class="accent">
    <tr>
        <td style="background: #00b4f0;"></td>
        <td style="background: #0a5bd7;"></td>
        <td style="background: #0b22a8;"></td>
    </tr>
</table>

<div class="page">
    <table class="top">
        <tr>
            <td>
                <div><img src="{{ public_path('assets/images/logo/taxivan-logo.png') }}" class="brand" alt="Taxivan"></div>
                <div><img src="{{ public_path('assets/images/logo/franja-roja.png') }}" class="brand-stripe" alt=""></div>
            </td>
            <td style="text-align: right;">
                @if($simulated)
                    <span class="tag tag-sim">SIMULACIÓN</span>
                @elseif($annulledAt)
                    <span class="tag tag-annulled">ANULADO</span>
                @endif
            </td>
        </tr>
    </table>

    <p class="eyebrow">Orden de salida</p>
    <h1 class="title">{{ $dayTitle }}</h1>
    <img src="{{ public_path('assets/images/logo/franja-roja.png') }}" class="stripe" alt="">
    <table class="chips">
        <tr>
            <td><span class="chip">{{ $simulated ? 'Simulación' : 'Sorteo' }} <b>{{ $simulated ? '' : '#'.$code }}</b></span></td>
            @if($drawnAt)
                <td><span class="chip">Sorteado <b>{{ $drawnAt }}</b></span></td>
            @endif
            <td><span class="chip"><b>{{ $participants }}</b> participantes</span></td>
        </tr>
    </table>

    @if($annulledAt)
        <div class="annulled">Este sorteo fue <b>anulado</b> el {{ $annulledAt }}; este orden ya no vale.</div>
    @endif

    <table class="list {{ $annulledAt ? 'annulled-list' : '' }}">
        @foreach(array_chunk($items, 3) as $r => $row)
            <tr>
                @foreach([0, 1, 2] as $c)
                    @if($c > 0)
                        <td class="gap"></td>
                    @endif
                    <td class="item">
                        @isset($row[$c])
                            @php $item = $row[$c]; @endphp
                            <div class="card {{ ($r * 3 + $c) < 3 ? 'top3' : '' }}">
                                <table class="inner">
                                    <tr>
                                        <td class="num-cell"><div class="num">{{ $item['number'] }}</div></td>
                                        <td>
                                            <div class="plate">{{ $item['plate'] }}</div>
                                            <div class="driver">{{ $item['driver'] }}</div>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        @endisset
                    </td>
                @endforeach
            </tr>
        @endforeach
    </table>

    <table class="foot">
        <tr>
            <td>
                @if($simulated)
                    <b>Simulación</b><br><span class="muted">No es un sorteo real ni se guardó.</span>
                @else
                    <b>Certificado por el sistema Taxivan</b><br><span class="muted">Sorteo #{{ $code }}</span>
                @endif
            </td>
            <td style="text-align: right;" class="muted">Generado el {{ $generatedAt }}</td>
        </tr>
    </table>
</div>
</body>
</html>
