@extends('layout.master')
@section('title', 'Snapshot de auditoría')

@section('main-content')
    <livewire:audit-snapshot :id="$id"/>
@endsection
