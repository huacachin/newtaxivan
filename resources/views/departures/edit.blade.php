@extends('layout.master')
@section('title', 'Salidas | Editar')
@section('css')

@endsection

@section('main-content')
    <livewire:departures.edit-departure :id="$id"   />
@endsection

@section('script')

@endsection
