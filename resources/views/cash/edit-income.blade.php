@extends('layout.master')
@section('title', 'Ingresos | Editar')
@section('main-content')
    <livewire:cash.edit-income :id="$incomeId" />
@endsection
