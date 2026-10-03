@extends('admin.layouts.app')
@section('title', 'Users')

@section('content')
<div class="card">
    <div class="card-header">Admin accounts</div>
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Joined</th><th class="text-end">Action</th></tr></thead>
            <tbody>
            @foreach ($users as $user)
                <tr>
                    <td>{{ $user->name }} @if ($user->is(auth()->user())) <span class="badge badge-soft-info ms-1">You</span> @endif</td>
                    <td>{{ $user->email }}</td>
                    <td><span class="badge badge-soft-{{ $user->isAdmin() ? 'primary' : 'secondary' }}">{{ ucfirst($user->role) }}</span></td>
                    <td><span class="badge badge-soft-{{ $user->is_active ? 'success' : 'danger' }}">{{ $user->is_active ? 'Active' : 'Inactive' }}</span></td>
                    <td>{{ $user->created_at->format('d M Y') }}</td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-key"></i> Change password</a>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
