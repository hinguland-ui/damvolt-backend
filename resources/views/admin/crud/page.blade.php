@extends('admin.layouts.app')
@section('title', $cfg['title'])

@section('content')
<div class="card">
    <div class="card-body">
        @include('admin.crud._manager', ['key' => $key, 'cfg' => $cfg])
    </div>
</div>
@endsection
