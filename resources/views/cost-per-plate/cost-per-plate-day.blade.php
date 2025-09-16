@extends('layout.master')
@section('title', 'Costo por placa')
@section('css')

@endsection

@section('main-content')
    <livewire:cost-per-plate.cost-per-plate-day :year="$year" :month="$month" />
@endsection

@section('script')

@endsection
