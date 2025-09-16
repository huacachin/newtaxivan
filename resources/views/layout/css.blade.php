<!-- Animation css -->
<link rel="stylesheet" href="{{ asset('assets/vendor/animation/animate.min.css') }}" >

<!-- Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@200;300;400;500;600;700;800;900&display=swap"
    rel="stylesheet">

<!-- Weather icon css-->
<link rel="stylesheet" type="text/css" href="{{ asset('assets/vendor/weather/weather-icons.css') }}">
<link rel="stylesheet" type="text/css" href="{{ asset('assets/vendor/weather/weather-icons-wind.css') }}">

  <!--font-awesome-css-->
  <link rel="stylesheet" href="{{asset('assets/vendor/fontawesome/css/all.css')}}">

<!--Flag Icon css-->
<link rel="stylesheet" type="text/css" href="{{ asset('assets/vendor/flag-icons-master/flag-icon.css') }}">

<!-- Tabler icons-->
<link rel="stylesheet" type="text/css" href="{{ asset('assets/vendor/tabler-icons/tabler-icons.css') }}">

<!-- Prism css-->
<link rel="stylesheet" type="text/css" href="{{ asset('assets/vendor/prism/prism.min.css') }}">

<!-- Bootstrap css-->
<link rel="stylesheet" type="text/css" href="{{ asset('assets/vendor/bootstrap/bootstrap.min.css') }}">

<!-- Simplebar css-->
<link rel="stylesheet" type="text/css" href="{{ asset('assets/vendor/simplebar/simplebar.css') }}">

@yield('css')
<style>
    @font-face {
        font-family: 'brush';
        src: url({{asset('assets/fonts/brush.otf')}}) format('opentype');
        font-weight: normal;
        font-style: normal;
    }
    .logo-text{
        font-family: 'brush';
        font-size:42px;
    }
    .active-menu{
        color: rgba(var(--dark), 1);
        background: rgba(var(--dark), .08);
        border: 1px dashed rgba(var(--dark), .2);
    }
</style>
@vite(['public/assets/scss/style.scss'])
@livewireStyles
