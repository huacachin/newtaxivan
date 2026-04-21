@extends('layout.master')
@section('title', 'Conceptos | Editar')
@section('main-content')
    <livewire:concepts.edit :id="$conceptId" />
@endsection
