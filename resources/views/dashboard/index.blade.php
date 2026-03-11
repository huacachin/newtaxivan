@extends('layout.master')
@section('title', 'Dashboard')
@section('css')
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
@endsection

@section('main-content')
    <livewire:dashboard.index/>
@endsection

@section('script')

@endsection
