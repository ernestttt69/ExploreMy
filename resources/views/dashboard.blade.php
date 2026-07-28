<h1>Welcome {{ auth()->user()->name }}</h1>

<img src="{{ asset('storage/'.auth()->user()->profile_picture) }}" width="100">

<p>{{ auth()->user()->email }}</p>