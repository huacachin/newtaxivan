@extends('layout.master')
@section('title', 'Vehículos | Editar Vehiculo')
@section('css')

@endsection

@section('main-content')
    <livewire:vehicles.edit :id="$id"/>
@endsection

@section('script')

@endsection
