@extends('admin.layouts.app')
@section('title', 'Add User')

@section('content')
<div class="card">
    <div class="card-header">User Details</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.users.store') }}">
            @include('admin.users._form')
        </form>
    </div>
</div>
@endsection
