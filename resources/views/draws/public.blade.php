{{-- Orden de salida de un sorteo: página pública (sin login) que se comparte por
     WhatsApp, o un día de la simulación (solo con sesión). Sin el layout del sistema. --}}
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
    <link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/tabler-icons/tabler-icons.css') }}">

    <style>
        :root { --hc-cyan: #00b4f0; --hc-blue: #0a5bd7; --hc-royal: #0b22a8; --hc-navy: #0a1f6b; --hc-orange: #ff4a12; }
        body { font-family: 'Poppins', sans-serif; background: #eef3fb; min-height: 100vh; margin: 0; color: #1b2430; }
        .hc-page { position: relative; padding: 32px 16px 48px; }
        .hc-page::before { content: ''; position: absolute; inset: 0 0 auto 0; height: 260px; z-index: 0;
            background: linear-gradient(120deg, rgba(0, 180, 240, .18), rgba(11, 34, 168, .14)); border-radius: 0 0 50% 50% / 0 0 60px 60px; }
        .hc-order { position: relative; z-index: 1; max-width: 1080px; margin: 0 auto; background: #fff; border-radius: 16px;
            overflow: hidden; box-shadow: 0 18px 50px rgba(10, 31, 107, .18); }
        .hc-order-head { position: relative; color: #fff; padding: 24px 28px 20px;
            background: linear-gradient(120deg, var(--hc-cyan) 0%, var(--hc-blue) 45%, var(--hc-royal) 100%); }
        .hc-order-logo { height: 36px; width: auto; display: block; margin-bottom: 14px; }
        .hc-badge { position: absolute; top: 22px; right: 24px; color: #fff; font-weight: 700; font-size: 12px;
            letter-spacing: .08em; padding: 5px 12px; border-radius: 999px; background: var(--hc-orange); }
        .hc-badge-annulled { background: #dc2626; }
        .hc-order-title { font-size: 26px; font-weight: 700; margin: 0; }
        .hc-order-swoosh { display: block; width: min(440px, 85%); height: 12px; margin: 6px 0 18px; }
        .hc-order-meta { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
        .hc-order-meta span { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: .06em; opacity: .8; }
        .hc-order-meta b { font-size: 15px; font-weight: 600; }
        .hc-annulled { margin: 18px 28px 0; padding: 12px 16px; border-radius: 12px; background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .hc-order-body { padding: 22px 28px 8px; }
        .hc-order-label { font-size: 12px; text-transform: uppercase; letter-spacing: .08em; color: #6b7280; margin-bottom: 10px; }
        .hc-order-list { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(3, minmax(0, 1fr));
            border: 1px solid #e8ecf4; border-radius: 12px; overflow: hidden; }
        .hc-order-list.is-annulled { opacity: .55; }
        .hc-order-list li { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-bottom: 1px solid #eef1f7; min-width: 0; }
        .hc-num { flex: 0 0 32px; height: 32px; border-radius: 50%; display: grid; place-items: center; font-weight: 700; font-size: 13px;
            color: #fff; background: var(--hc-royal); }
        .hc-order-list li:nth-child(-n+3) .hc-num { background: var(--hc-orange); }
        .hc-plate { font-weight: 700; color: var(--hc-navy); letter-spacing: .02em; }
        .hc-driver { color: #4b5563; font-size: 13px; line-height: 1.25; overflow: hidden;
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
        .hc-order-foot { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; padding: 16px 28px 22px; border-top: 1px solid #eef1f7; margin-top: 14px; }
        .hc-order-seal { width: 46px; height: 46px; }
        .hc-btn-wa { background: #25d366; color: #fff; border: 0; }
        .hc-btn-wa:hover { background: #1ebe5a; color: #fff; }
        .hc-reveal { opacity: 0; animation: hc-reveal .45s ease forwards; }
        @keyframes hc-reveal { from { opacity: 0; transform: translateY(8px) scale(.97); } to { opacity: 1; transform: none; } }
        @media (max-width: 768px) {
            .hc-order-meta { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .hc-order-list { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .hc-order-title { font-size: 21px; }
        }
        @media (max-width: 520px) {
            .hc-page { padding: 16px 12px 32px; }
            .hc-order-list { grid-template-columns: minmax(0, 1fr); }
            .hc-order-head, .hc-order-body, .hc-order-foot { padding-left: 16px; padding-right: 16px; }
            .hc-annulled { margin-left: 16px; margin-right: 16px; }
            .hc-badge { top: 16px; right: 16px; }
        }
        @media print {
            body { background: #fff; }
            .hc-page { padding: 0; }
            .hc-page::before, .hc-no-print { display: none !important; }
            .hc-order { box-shadow: none; max-width: none; }
            .hc-order-head, .hc-num { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .hc-reveal { opacity: 1; animation: none; }
        }
    </style>
</head>
<body>
<div class="hc-page">
    <div class="hc-order">
        <div class="hc-order-head">
            <img src="{{ asset('assets/images/logo/logo1.png') }}" class="hc-order-logo" alt="Huacachin">
            @if($simulated)
                <span class="hc-badge">SIMULACIÓN</span>
            @elseif($annulledAt)
                <span class="hc-badge hc-badge-annulled">ANULADO</span>
            @endif
            <h1 class="hc-order-title">{{ $title }}</h1>
            <svg class="hc-order-swoosh" viewBox="0 0 420 12" preserveAspectRatio="none" aria-hidden="true">
                <path d="M0 9 C 110 3, 290 1, 420 3 L 420 4.5 C 300 4, 150 6, 70 12 Z" fill="#ff4a12"/>
            </svg>
            <div class="hc-order-meta">
                <div><span>Sorteo</span><b>{{ $simulated ? 'Simulación' : '#'.$code }}</b></div>
                <div><span>Fecha del sorteo</span><b>{{ $drawnAt ?? '—' }}</b></div>
                <div><span>Participantes</span><b>{{ $participants }}</b></div>
                <div><span>Tipo</span><b>Rotación bajo · medio · alto</b></div>
            </div>
        </div>

        @if($annulledAt)
            <div class="hc-annulled">
                <i class="ti ti-ban"></i> Este sorteo fue <b>anulado</b> el {{ $annulledAt }}; este orden ya no vale.
                @if($replacementUrl)
                    <a href="{{ $replacementUrl }}" class="fw-semibold">Ver el sorteo vigente de ese día →</a>
                @endif
            </div>
        @endif

        <div class="hc-order-body">
            <div class="hc-order-label">Orden de salida</div>
            <ol class="hc-order-list {{ $annulledAt ? 'is-annulled' : '' }}">
                @foreach($items as $i => $item)
                    <li @if($reveal) class="hc-reveal" style="animation-delay: {{ $i * 70 }}ms" @endif>
                        <span class="hc-num">{{ $item['number'] }}</span>
                        <span class="hc-plate">{{ $item['plate'] }}</span>
                        <span class="hc-driver" title="{{ $item['driver'] }}">{{ $item['driver'] }}</span>
                    </li>
                @endforeach
            </ol>
        </div>

        <div class="hc-order-foot">
            <img src="{{ asset('assets/images/logo/logo-sello.png') }}" class="hc-order-seal" alt="">
            <div class="small lh-sm">
                @if($simulated)
                    <b class="text-danger">Simulación:</b> no es un sorteo real ni se guardó.
                @else
                    <b>Certificado por el sistema Huacachin</b>
                    <br><span class="text-muted">Sorteo #{{ $code }}</span>
                @endif
            </div>
            <div class="ms-auto d-flex flex-wrap gap-2 hc-no-print">
                @if($whatsappUrl)
                    <a class="btn btn-sm hc-btn-wa" href="{{ $whatsappUrl }}" target="_blank" rel="noopener">
                        <i class="ti ti-brand-whatsapp"></i> Enviar por WhatsApp
                    </a>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="hcCopy" data-url="{{ $shareUrl }}">
                        <i class="ti ti-link"></i> <span>Copiar link</span>
                    </button>
                @endif
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                    <i class="ti ti-printer"></i> Imprimir
                </button>
            </div>
        </div>
    </div>
</div>

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
