@extends('layout.master')
@section('title', 'Usuarios | Permisos de usuario')
@section('css')

@endsection

@section('main-content')
    <livewire:users.perms :id="$id"   />
@endsection

@section('script')

@endsection
