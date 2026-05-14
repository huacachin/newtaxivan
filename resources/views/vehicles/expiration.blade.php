@extends('layout.master')
@section('title', 'Vehículos | Actualizar vencimiento')
@section('css')

@endsection

@section('main-content')
    <livewire:vehicles.expiration-edit :id="$id" :field="$field"/>
@endsection

@section('script')

@endsection
