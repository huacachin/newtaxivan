@extends('layout.master')
@section('title', 'Pagos | Editar')
@section('css')

@endsection

@section('main-content')
    <livewire:payments.edit-payment :id="$id"/>
@endsection

@section('script')

@endsection
