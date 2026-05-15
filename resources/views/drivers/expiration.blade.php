@extends('layout.master')
@section('title', 'Conductores | Actualizar vencimiento')
@section('css')

@endsection

@section('main-content')
    <livewire:drivers.expiration-edit :id="$id" :field="$field"/>
@endsection

@section('script')

@endsection
