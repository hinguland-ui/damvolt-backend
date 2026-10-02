@extends('admin.layouts.app')
@section('title', $schema['title'])

@section('content')
<p class="text-muted mb-3">{{ $schema['subtitle'] }}</p>

<div class="card">
    <ul class="nav cms-tabs px-2 pt-1" role="tablist">
        @foreach ($schema['tabs'] as $tab)
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $loop->first ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#pane-{{ $tab['key'] }}"
                    data-tab-key="{{ $tab['key'] }}" type="button" role="tab">
                    <i class="bi {{ $tab['icon'] }}"></i> {{ $tab['title'] }}
                </button>
            </li>
        @endforeach
    </ul>

    <div class="card-body tab-content p-4">
        @foreach ($schema['tabs'] as $tab)
            <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="pane-{{ $tab['key'] }}" role="tabpanel">
                <div class="tab-help">{{ $tab['help'] ?? '' }}</div>

                @isset($tab['fields'])
                    <form method="POST" action="{{ route('admin.sections.update', [$group, $tab['key']]) }}" enctype="multipart/form-data">
                        @csrf @method('PUT')
                        <div class="row g-3">
                            @foreach ($tab['fields'] as $f)
                                @include('admin.components.field', ['f' => $f, 'name' => $f['name'], 'value' => $data[$tab['key']][$f['name']] ?? null])
                            @endforeach
                        </div>
                        <div class="section-actions">
                            <button class="btn btn-primary"><i class="bi bi-check2"></i> Save {{ $tab['title'] }}</button>
                        </div>
                    </form>
                    @isset($tab['after'])
                        @include($tab['after'])
                    @endisset
                @endisset

                @isset($tab['crud'])
                    @isset($tab['fields'])
                        <div class="card-section-title">{{ $cruds[$tab['crud']]['title'] }}</div>
                    @endisset
                    @include('admin.crud._manager', ['key' => $tab['crud'], 'cfg' => $cruds[$tab['crud']]])
                @endisset
            </div>
        @endforeach
    </div>
</div>
@endsection
