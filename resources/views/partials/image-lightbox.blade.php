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

            // Pre-carga cada URL para detectar el tamano natural real de la
            // imagen. Sin esto PhotoSwipe usa el width/height declarados y
            // puede estirar fotos pequenas hacia arriba.
            function _loadDimensions(urls) {
                return Promise.all(urls.map(function (url) {
                    return new Promise(function (resolve) {
                        var img = new Image();
                        img.onload = function () {
                            resolve({
                                src: url,
                                width: img.naturalWidth || 1600,
                                height: img.naturalHeight || 1200,
                            });
                        };
                        img.onerror = function () {
                            resolve({ src: url, width: 1600, height: 1200 });
                        };
                        img.src = url;
                    });
                }));
            }

            window.addEventListener('open-lightbox', function (e) {
                var detail = (e && e.detail) || {};
                var images = Array.isArray(detail.images) ? detail.images : [];
                if (!images.length) return;

                var index = Number.isInteger(detail.index) ? detail.index : 0;

                _loadDimensions(images).then(function (items) {
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
                        // Muestra al tamano natural (1:1) salvo que la imagen
                        // sea mas grande que el viewport: ahi la reduce con 'fit'.
                        // Nunca hace upscale.
                        initialZoomLevel: function (zl) {
                            return Math.min(1, zl.fit);
                        },
                        secondaryZoomLevel: 3,
                        maxZoomLevel: 6,
                        indexIndicatorSep: ' / ',
                    });

                    pswp.init();
                });
            });
        </script>
    @endpush
@endonce
