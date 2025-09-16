<!DOCTYPE html>
<html lang="en">

<head>
    <!-- All meta and title start-->
@include('layout.head')
<!-- meta and title end-->

    <!-- css start-->
@include('layout.css')
<!-- css end-->
</head>

<body text="small-text">
<!-- Loader start-->
<div class="app-wrapper">

    <div class="loader-wrapper">
        <div class="app-loader">
            <span></span>
            <span></span>
            <span></span>
            <span></span>
            <span></span>
        </div>
    </div>
    <!-- Loader end-->

    <!-- Menu Navigation start -->
@include('layout.sidebar')
<!-- Menu Navigation end -->


    <div class="app-content">
        <!-- Header Section start -->
    @include('layout.header')
    <!-- Header Section end -->

        <!-- Main Section start -->
        <main>
            {{-- main body content --}}
            @yield('main-content')
        </main>
        <!-- Main Section end -->
    </div>

    <!-- tap on top -->
    <div class="go-top">
      <span class="progress-value">
        <i class="ti ti-arrow-up"></i>
      </span>
    </div>

    <!-- Footer Section start -->
     @include('layout.footer')
    <!-- Footer Section end -->
</div>
</body>

<!--customizer-->
<!--div id="customizer"></div-->

<!-- scripts start-->
@include('layout.script')
<!-- scripts end-->
<script src="{{asset('assets/vendor/sweetalert/sweetalert.js')}}"></script>

<script>

    function openModal(id, opts = {}) {
        const el = document.getElementById(id);
        if (!el) return;

        // recordar quién abrió para devolverle el foco al cerrar (nuestro helper de cerrar ya lo usa)
        const opener = document.activeElement;
        if (opener) opener.setAttribute('data-open', id);

        const instance = bootstrap.Modal.getOrCreateInstance(el /*, {backdrop:'static', keyboard:true}*/);
        instance.show();

        // Foco cuando el modal termine de mostrarse
        const focusSelector = opts.focus || '[autofocus], input:not([type=hidden]):not([disabled]), select, textarea, button';
        const onShown = () => {
            el.removeEventListener('shown.bs.modal', onShown);
            const target = el.querySelector(focusSelector);
            if (target) target.focus();
        };
        el.addEventListener('shown.bs.modal', onShown, {once:true});
    }

    function hideModal(id) {
        const modalEl = document.getElementById(id);
        if(!modalEl) return;

        // 1) Si el foco está dentro del modal, quítalo
        const active = document.activeElement;
        if (active && modalEl.contains(active) && typeof active.blur === 'function') {
            active.blur();
        }

        // 2) (Opcional) devolver foco al disparador del modal si lo tienes marcado
        const trigger = document.querySelector(`[data-open="${id}"]`);
        if (trigger) trigger.focus();

        // 3) Cerrar en el siguiente frame para que el blur “asiente”
        requestAnimationFrame(() => {
            bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        });
    }

    function successAlert(message) {
        Swal.fire({
            icon: 'success',
            title: 'Success',
            text: message,
            confirmButtonText: 'OK',
        });
    }

    document.addEventListener('open-modal', event => {
        openModal(event.detail[0]['name'], {focus:"#" + event.detail[0]['focus']});
    });

    window.addEventListener('modal-close', event => {
        hideModal(event.detail[0]['name']);
    });

    window.addEventListener('successAlert', event => {
        successAlert(event.detail[0]['message']);
    });

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('#down');
        if (!btn) return;

        const h = Math.max(
            document.body.scrollHeight,
            document.documentElement.scrollHeight
        );
        window.scrollTo({ top: h, behavior: 'smooth' });
    });

    window.addEventListener('url-open', event => {
        window.location.href = event.detail[0]['url'];
    });

    window.addEventListener('go-back', (e) => {
        const fb = e.detail?.fallback || '/';
        if (history.length > 1) history.back();
        else location.href = fb;
    });

</script>

</html>
