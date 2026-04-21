@extends('layout.master')
@section('title', 'Egresos | Editar')
@section('main-content')
    <livewire:cash.edit-expense :id="$expenseId" />
@endsection
