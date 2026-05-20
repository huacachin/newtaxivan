{{--
    Lightbox global basado en PhotoSwipe v5.4.x (modo programatico).
    Escucha el evento window `open-lightbox` con detail = { images: [url,...], index: 0 }.
    Soporta zoom (pinch / wheel / double-tap), pan al estar zoomed, swipe-down to close,
    flechas y ESC.

    Disparadores existentes en el proyecto:
        x-on:click="$dispatch('open-lightbox', { images: urls, index: 0 })"

    Usa @once: el partial puede incluirse mas de una vez en la pagina sin duplicar el handler.
--}}
@once
    @push('styles')
        <link rel="stylesheet" href="{{ asset('assets/js/photoswipe/photoswipe.css') }}">
    @endpush
    @push('scripts')
        <script type="module">
            import PhotoSwipe from "{{ asset('assets/js/photoswipe/photoswipe.esm.js') }}";

            window.addEventListener('open-lightbox', function (e) {
                var detail = (e && e.detail) || {};
                var images = Array.isArray(detail.images) ? detail.images : [];
                if (!images.length) return;

                var index = Number.isInteger(detail.index) ? detail.index : 0;

                // PhotoSwipe necesita width/height; usamos un placeholder 1600x1200
                // (no afecta el zoom, solo la animacion de apertura).
                var items = images.map(function (u) {
                    return { src: u, width: 1600, height: 1200 };
                });

                var pswp = new PhotoSwipe({
                    dataSource: items,
                    index: index,
                    bgOpacity: 0.92,
                    pinchToClose: true,
                    closeOnVerticalDrag: true,
                    wheelToZoom: true,
                    imageClickAction: 'zoom',
                    tapAction: 'zoom',
                    doubleTapAction: 'zoom',
                    initialZoomLevel: 'fit',
                    secondaryZoomLevel: 3,
                    maxZoomLevel: 6,
                    indexIndicatorSep: ' / ',
                });

                pswp.init();
            });
        </script>
    @endpush
@endonce
