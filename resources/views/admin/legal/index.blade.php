@extends('admin.layouts.app')
@section('title', 'Legal Pages')

@section('content')
<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span>Legal pages <span class="text-muted fw-normal">({{ $pages->count() }}) · shown in the website footer · drag <i class="bi bi-grip-vertical"></i> to re-order</span></span>
        <a href="{{ route('admin.legal.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Add page</a>
    </div>
    <div class="card-body">
        <div class="sort-list js-sort-list" data-url="{{ route('admin.legal.reorder') }}">
            @forelse ($pages as $p)
                <div class="sort-item {{ $p->is_active ? '' : 'is-off' }}" data-id="{{ $p->id }}">
                    <span class="drag-handle" title="Drag to re-order"><i class="bi bi-grip-vertical"></i></span>
                    <span class="pos">{{ $loop->iteration }}</span>
                    <div class="info">
                        <div class="t">{{ $p->title }}</div>
                        <div class="s">/{{ $p->slug }} · updated {{ $p->updated_at->diffForHumans() }}</div>
                    </div>
                    @unless ($p->is_active) <span class="badge badge-soft-secondary">Hidden</span> @endunless
                    <a href="{{ route('admin.legal.edit', $p) }}" class="btn btn-sm btn-light border"><i class="bi bi-pencil"></i> Edit</a>
                    <form method="POST" action="{{ route('admin.legal.destroy', $p) }}" class="js-delete" data-title="Delete “{{ $p->title }}”?">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-light border text-danger" title="Delete"><i class="bi bi-trash"></i></button>
                    </form>
                </div>
            @empty
                <div class="sort-empty">No legal pages yet.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
