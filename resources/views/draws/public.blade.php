{{-- Orden de salida de un sorteo: página pública (sin login) que se comparte por
     WhatsApp, o un día de la simulación (solo con sesión). Sin el layout del sistema.
     Diseño limpio en blanco con el logo de Huacachin, su franja roja bajo la fecha
     y la lista en tarjetas de 3 columnas (2 en tablet, 1 en celular). --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }} | Huacachin</title>
    <link rel="icon" href="{{ asset('assets/images/logo/logo-sello.png') }}" type="image/png">

    {{-- Vista previa del link en WhatsApp --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Huacachin">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $simulated ? 'Simulación' : 'Sorteo '.$code }} · {{ $participants }} participantes">
    <meta property="og:image" content="{{ asset('assets/images/logo/logo-og.png') }}">
    @if($shareUrl)
        <meta property="og:url" content="{{ $shareUrl }}">
    @endif

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/vendor/tabler-icons/tabler-icons.css') }}">

    <style>
        :root { --hc-ink: #0f172a; --hc-muted: #64748b; --hc-line: #e9edf3; --hc-soft: #f4f6fa;
            --hc-cyan: #00b4f0; --hc-blue: #0b22a8; --hc-red: #ff4a12; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Poppins', sans-serif; color: var(--hc-ink);
            background: linear-gradient(180deg, #e9f1fb 0, #f4f6fa 320px) no-repeat, #f4f6fa; }
        .hc-sheet { max-width: 1080px; margin: 40px auto; background: #fff; border: 1px solid var(--hc-line); border-radius: 18px;
            box-shadow: 0 12px 34px rgba(15, 23, 42, .07); overflow: hidden; }
        .hc-accent { height: 5px; background: linear-gradient(90deg, var(--hc-cyan), var(--hc-blue)); }
        .hc-head { padding: 30px 40px 6px; }
        .hc-top { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 26px; }
        .hc-logo { height: 40px; width: auto; display: block; }
        .hc-tag { font-size: 11px; font-weight: 600; letter-spacing: .1em; padding: 5px 12px; border-radius: 999px; }
        .hc-tag-sim { color: var(--hc-red); background: rgba(255, 74, 18, .1); }
        .hc-tag-annulled { color: #b91c1c; background: #fee2e2; }
        .hc-eyebrow { margin: 0 0 4px; font-size: 12px; font-weight: 600; letter-spacing: .14em; text-transform: uppercase; color: var(--hc-blue); }
        .hc-title { margin: 0; font-size: 32px; font-weight: 700; letter-spacing: -.02em; line-height: 1.15; }
        .hc-stripe { display: block; width: 260px; max-width: 75%; height: 11px; margin: 10px 0 18px; }
        .hc-chips { display: flex; flex-wrap: wrap; gap: 8px; }
        .hc-chip { display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; color: var(--hc-muted);
            background: var(--hc-soft); border-radius: 999px; padding: 6px 12px; }
        .hc-chip b { color: var(--hc-ink); font-weight: 600; }
        .hc-chip i { font-size: 15px; color: var(--hc-blue); }
        .hc-annulled { margin: 18px 40px 0; padding: 12px 16px; border-radius: 12px; font-size: 13px; color: #991b1b; background: #fef2f2; border: 1px solid #fecaca; }
        .hc-annulled a { color: #991b1b; font-weight: 600; }
        .hc-body { padding: 26px 40px 8px; }
        .hc-list { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
        .hc-list.is-annulled { opacity: .45; }
        .hc-item { display: flex; align-items: center; gap: 12px; min-width: 0; padding: 11px 12px; border: 1px solid var(--hc-line); border-radius: 12px; background: #fff; }
        .hc-num { flex: 0 0 38px; height: 38px; border-radius: 10px; display: grid; place-items: center; font-weight: 700; font-size: 15px;
            font-variant-numeric: tabular-nums; color: var(--hc-blue); background: rgba(11, 34, 168, .07); }
        .hc-item.is-top { border-color: rgba(255, 74, 18, .3); background: linear-gradient(90deg, rgba(255, 74, 18, .05), #fff 60%); }
        .hc-item.is-top .hc-num { color: #fff; background: var(--hc-red); }
        .hc-info { min-width: 0; }
        .hc-plate { display: block; font-weight: 700; font-size: 15px; letter-spacing: .04em; line-height: 1.2; }
        .hc-driver { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
            margin-top: 2px; font-size: 12.5px; line-height: 1.3; color: var(--hc-muted); }
        .hc-foot { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 14px;
            margin-top: 26px; padding: 18px 40px 24px; border-top: 1px solid var(--hc-line); background: #fbfcfe; }
        .hc-cert { display: flex; align-items: center; gap: 10px; font-size: 12.5px; color: var(--hc-muted); line-height: 1.35; }
        .hc-cert img { width: 40px; height: 40px; }
        .hc-cert b { color: var(--hc-ink); font-weight: 600; }
        .hc-actions { display: flex; flex-wrap: wrap; gap: 8px; }
        .hc-btn { display: inline-flex; align-items: center; gap: 6px; font: 500 13px 'Poppins', sans-serif; color: var(--hc-ink); background: #fff;
            border: 1px solid #dfe4ea; border-radius: 10px; padding: 8px 14px; text-decoration: none; cursor: pointer; }
        .hc-btn:hover { background: var(--hc-soft); }
        .hc-btn-wa { color: #fff; background: #25d366; border-color: #25d366; }
        .hc-btn-wa:hover { background: #1ebe5a; }
        .hc-reveal { opacity: 0; animation: hc-reveal .45s ease forwards; }
        @keyframes hc-reveal { from { opacity: 0; transform: translateY(8px) scale(.98); } to { opacity: 1; transform: none; } }
        @media (max-width: 900px) {
            .hc-list { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 600px) {
            .hc-sheet { margin: 0; border: 0; border-radius: 0; box-shadow: none; min-height: 100vh; }
            .hc-head { padding: 22px 18px 4px; }
            .hc-body { padding: 20px 18px 6px; }
            .hc-foot { padding: 16px 18px 22px; }
            .hc-annulled { margin: 16px 18px 0; }
            .hc-logo { height: 34px; }
            .hc-title { font-size: 25px; }
            .hc-list { grid-template-columns: minmax(0, 1fr); gap: 8px; }
        }
        @media print {
            body { background: #fff; }
            .hc-sheet { margin: 0; border: 0; box-shadow: none; max-width: none; }
            .hc-actions { display: none; }
            .hc-reveal { opacity: 1; animation: none; }
            .hc-accent, .hc-num, .hc-item.is-top { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .hc-list { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        }
    </style>
</head>
<body>
<main class="hc-sheet">
    <div class="hc-accent"></div>

    <header class="hc-head">
        <div class="hc-top">
            <img src="{{ asset('assets/images/logo/logo-color.png') }}" class="hc-logo" alt="Huacachin">
            @if($simulated)
                <span class="hc-tag hc-tag-sim">SIMULACIÓN</span>
            @elseif($annulledAt)
                <span class="hc-tag hc-tag-annulled">ANULADO</span>
            @endif
        </div>

        <p class="hc-eyebrow">Orden de salida</p>
        <h1 class="hc-title">{{ $dayTitle }}</h1>
        <svg class="hc-stripe" viewBox="0 0 420 12" preserveAspectRatio="none" aria-hidden="true">
            <path d="M0 9 C 110 3, 290 1, 420 3 L 420 4.5 C 300 4, 150 6, 70 12 Z" fill="#ff4a12"/>
        </svg>
        <div class="hc-chips">
            <span class="hc-chip"><i class="ti ti-hash"></i>{{ $simulated ? 'Simulación' : 'Sorteo' }} <b>{{ $simulated ? '' : $code }}</b></span>
            @if($drawnAt)
                <span class="hc-chip"><i class="ti ti-clock"></i><b>{{ $drawnAt }}</b></span>
            @endif
            <span class="hc-chip"><i class="ti ti-car"></i><b>{{ $participants }}</b> participantes</span>
        </div>
    </header>

    @if($annulledAt)
        <div class="hc-annulled">
            <i class="ti ti-ban"></i> Este sorteo fue <b>anulado</b> el {{ $annulledAt }}; este orden ya no vale.
            @if($replacementUrl)
                <a href="{{ $replacementUrl }}">Ver el sorteo vigente →</a>
            @endif
        </div>
    @endif

    <section class="hc-body">
        <ol class="hc-list {{ $annulledAt ? 'is-annulled' : '' }}">
            @foreach($items as $i => $item)
                <li class="hc-item {{ $i < 3 ? 'is-top' : '' }} {{ $reveal ? 'hc-reveal' : '' }}" @if($reveal) style="animation-delay: {{ $i * 60 }}ms" @endif>
                    <span class="hc-num">{{ $item['number'] }}</span>
                    <span class="hc-info">
                        <span class="hc-plate">{{ $item['plate'] }}</span>
                        <span class="hc-driver" title="{{ $item['driver'] }}">{{ $item['driver'] }}</span>
                    </span>
                </li>
            @endforeach
        </ol>
    </section>

    <footer class="hc-foot">
        <div class="hc-cert">
            <img src="{{ asset('assets/images/logo/logo-sello.png') }}" alt="">
            <span>
                @if($simulated)
                    <b>Simulación</b><br>No es un sorteo real ni se guardó.
                @else
                    <b>Certificado por el sistema Huacachin</b><br>Sorteo #{{ $code }}
                @endif
            </span>
        </div>
        <div class="hc-actions">
            @if($whatsappUrl)
                <a class="hc-btn hc-btn-wa" href="{{ $whatsappUrl }}" target="_blank" rel="noopener"><i class="ti ti-brand-whatsapp"></i> WhatsApp</a>
                <button type="button" class="hc-btn" id="hcCopy" data-url="{{ $shareUrl }}"><i class="ti ti-link"></i> <span>Copiar link</span></button>
            @endif
            <button type="button" class="hc-btn" onclick="window.print()"><i class="ti ti-printer"></i> Imprimir</button>
        </div>
    </footer>
</main>

<script>
    (function () {
        var btn = document.getElementById('hcCopy');
        if (!btn) { return; }
        btn.addEventListener('click', function () {
            var label = btn.querySelector('span');
            var done = function () { label.textContent = '¡Copiado!'; setTimeout(function () { label.textContent = 'Copiar link'; }, 2000); };
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(btn.dataset.url).then(done);
            } else {
                var input = document.createElement('input');
                input.value = btn.dataset.url;
                document.body.appendChild(input);
                input.select();
                document.execCommand('copy');
                input.remove();
                done();
            }
        });
    })();
</script>
</body>
</html>
