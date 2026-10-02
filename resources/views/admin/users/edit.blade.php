@extends('admin.layouts.app')
@section('title', 'Edit User')

@section('content')
<div class="card">
    <div class="card-header">Edit {{ $user->name }}</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.users.update', $user) }}">
            @method('PUT')
            @include('admin.users._form')
        </form>
    </div>
</div>
@endsection
