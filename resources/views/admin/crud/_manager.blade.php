{{-- List + add/edit modal for one manager defined in App\Admin\Cruds. Vars: $key, $cfg --}}
@php
    $items = $cfg['model']::orderBy('sort_order')->orderBy('id')->get();
    $imageFields = collect($cfg['fields'])->where('type', 'image')->pluck('name');
@endphp

<div class="js-crud" data-store="{{ route('admin.crud.store', $key) }}" data-noun="{{ $cfg['singular'] }}">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div class="text-muted small">
            <b>{{ $items->count() }}</b> {{ \Illuminate\Support\Str::plural($cfg['singular'], $items->count()) }}
            @if ($items->count() > 1) · drag <i class="bi bi-grip-vertical"></i> to re-order @endif
        </div>
        <button type="button" class="btn btn-primary btn-sm js-crud-add"><i class="bi bi-plus-lg"></i> Add {{ $cfg['singular'] }}</button>
    </div>

    <div class="sort-list js-sort-list" data-url="{{ route('admin.crud.reorder', $key) }}">
        @forelse ($items as $item)
            @php
                $payload = collect($cfg['fields'])->mapWithKeys(fn ($f) => [$f['name'] => $item->{$f['name']}])->all();
                foreach ($imageFields as $im) {
                    $payload[$im . '_url'] = \App\Support\Media::url($item->{$im});
                }
                $thumb = isset($cfg['thumb']) ? \App\Support\Media::url($item->{$cfg['thumb']}) : null;
            @endphp
            <div class="sort-item {{ $item->is_active ? '' : 'is-off' }}" data-id="{{ $item->id }}">
                <span class="drag-handle" title="Drag to re-order"><i class="bi bi-grip-vertical"></i></span>
                <span class="pos">{{ $loop->iteration }}</span>
                @if ($thumb) <img class="thumb" src="{{ $thumb }}" alt="" loading="lazy"> @endif
                <div class="info">
                    <div class="t">{{ $item->{$cfg['primary']} }}</div>
                    @if (! empty($cfg['secondary']) && $item->{$cfg['secondary']})
                        <div class="s">{{ $item->{$cfg['secondary']} }}</div>
                    @endif
                </div>
                @unless ($item->is_active) <span class="badge badge-soft-secondary">Hidden</span> @endunless
                <button type="button" class="btn btn-sm btn-light border js-crud-edit"
                    data-url="{{ route('admin.crud.update', [$key, $item->id]) }}"
                    data-item="{{ json_encode($payload, JSON_HEX_APOS | JSON_HEX_QUOT) }}"><i class="bi bi-pencil"></i> Edit</button>
                <form method="POST" action="{{ route('admin.crud.destroy', [$key, $item->id]) }}" class="js-delete" data-title="Delete this {{ $cfg['singular'] }}?">
                    @csrf @method('DELETE')
                    <input type="hidden" name="_return" value="">
                    <button class="btn btn-sm btn-light border text-danger" title="Delete"><i class="bi bi-trash"></i></button>
                </form>
            </div>
        @empty
            <div class="sort-empty">Nothing here yet — click “Add {{ $cfg['singular'] }}” to create the first one.</div>
        @endforelse
    </div>

    <div class="modal fade" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
            <form class="modal-content" method="POST" enctype="multipart/form-data" action="{{ route('admin.crud.store', $key) }}">
                @csrf
                <input type="hidden" name="_method" value="PUT" disabled>
                <input type="hidden" name="_return" value="">
                <div class="modal-header">
                    <h5 class="modal-title fs-6 fw-bold">Add {{ $cfg['singular'] }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        @foreach ($cfg['fields'] as $f)
                            @include('admin.components.field', ['f' => $f, 'name' => $f['name'], 'value' => null, 'noOld' => true])
                        @endforeach
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
