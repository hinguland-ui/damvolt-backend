@extends('admin.layouts.app')
@section('title', 'Services')

@section('content')
<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span>All services <span class="text-muted fw-normal">({{ $services->count() }}) · drag <i class="bi bi-grip-vertical"></i> to re-order</span></span>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.crud.index', 'categories') }}" class="btn btn-light border btn-sm"><i class="bi bi-tags"></i> Manage tags</a>
            <a href="{{ route('admin.services.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Add service</a>
        </div>
    </div>
    <div class="card-body">
        <div class="sort-list js-sort-list" data-url="{{ route('admin.services.reorder') }}">
            @forelse ($services as $s)
                <div class="sort-item {{ $s->is_active ? '' : 'is-off' }}" data-id="{{ $s->id }}">
                    <span class="drag-handle" title="Drag to re-order"><i class="bi bi-grip-vertical"></i></span>
                    <span class="pos">{{ $loop->iteration }}</span>
                    <img class="thumb" src="{{ \App\Support\Media::url($s->image) }}" alt="" loading="lazy">
                    <div class="info">
                        <div class="t">{{ $s->title }}</div>
                        <div class="s">/services/{{ $s->slug }}</div>
                    </div>
                    @if ($s->category) <span class="badge badge-soft-primary">{{ $s->category->name }}</span> @endif
                    @unless ($s->is_active) <span class="badge badge-soft-secondary">Hidden</span> @endunless
                    <a href="{{ route('admin.services.edit', $s) }}" class="btn btn-sm btn-light border"><i class="bi bi-pencil"></i> Edit</a>
                    <form method="POST" action="{{ route('admin.services.destroy', $s) }}" class="js-delete" data-title="Delete “{{ $s->title }}”?">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-light border text-danger" title="Delete"><i class="bi bi-trash"></i></button>
                    </form>
                </div>
            @empty
                <div class="sort-empty">No services yet. <a href="{{ route('admin.services.create') }}">Add the first one</a>.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
