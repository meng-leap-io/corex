@extends('desktop.layout')

@section('title', 'Files - Corex')

@section('content')
<div class="flex flex-col h-full">
    @livewire('files.file-manager')
</div>
@endsection
