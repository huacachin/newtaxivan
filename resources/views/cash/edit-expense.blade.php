@extends('layout.master')
@section('main-content')
    <livewire:cash.edit-expense :id="$expenseId" />
@endsection
