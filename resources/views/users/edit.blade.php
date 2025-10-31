@extends('layout.master')
@section('title', 'Usuarios | Editar usuario')
@section('css')

@endsection

@section('main-content')
    <livewire:users.edit :id="$id"   />
@endsection

@section('script')

@endsection
