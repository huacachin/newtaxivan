@extends('layout.master')
@section('title', 'Propietarios | Actualizar vencimiento')
@section('css')

@endsection

@section('main-content')
    <livewire:owners.expiration-edit :id="$id" :field="$field"/>
@endsection

@section('script')

@endsection
