@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="dashboard-container">
    <h1>
        Welcome {{ auth()->user()->name }}
    </h1>

    <img
        src="{{ asset('storage/' . auth()->user()->profile_picture) }}"
        width="100"
        alt="Profile picture"
    >

    <p>
        {{ auth()->user()->email }}
    </p>
</div>
@endsection