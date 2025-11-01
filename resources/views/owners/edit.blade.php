@extends('layout.master')
@section('title', 'Propietarios')
@section('css')

@endsection

@section('main-content')
    <livewire:owners.edit :id="$id" />
@endsection

@section('script')

@endsection
