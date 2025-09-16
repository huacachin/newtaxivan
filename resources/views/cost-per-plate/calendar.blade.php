@extends('layout.master')
@section('title', 'Costo por placa - Días')
@section('css')

@endsection

@section('main-content')
    <livewire:cost-per-plate.calendar :plate="$plate" :year="(int)request('year')" :month="(int)request('month')"/>
@endsection

@section('script')

@endsection
