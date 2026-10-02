@extends('admin.layouts.app')
@section('title', $service->exists ? 'Edit service' : 'Add service')

@php
    $offerings = old('offerings', collect($service->offerings ?? [])->map(fn ($t) => ['text' => $t])->all());
    $benefits = old('benefits', $service->benefits ?? []);
    $applications = old('applications', $service->applications ?? []);
    $imgUrl = \App\Support\Media::url($service->image);
@endphp

@section('content')
<form method="POST" enctype="multipart/form-data"
    action="{{ $service->exists ? route('admin.services.update', $service) : route('admin.services.store') }}">
    @csrf
    @if ($service->exists) @method('PUT') @endif

    <div class="row g-3">
        {{-- ---------- Main column ---------- --}}
        <div class="col-xl-8">
            <div class="card mb-3">
                <div class="card-header">Service details</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">Service name</label>
                            <input type="text" id="title" name="title" value="{{ old('title', $service->title) }}" class="form-control @error('title') is-invalid @enderror" required>
                            @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">URL slug</label>
                            <div class="input-group slug-input">
                                <span class="input-group-text">/services/</span>
                                <input type="text" name="slug" value="{{ old('slug', $service->slug) }}" data-slug-source="#title" class="form-control @error('slug') is-invalid @enderror" required>
                            </div>
                            @error('slug') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            <div class="form-hint">Auto-generated from the name. Edit it to customise.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Short description <span class="text-muted fw-normal">(shown on service cards)</span></label>
                            <textarea name="short" rows="2" class="form-control @error('short') is-invalid @enderror">{{ old('short', $service->short) }}</textarea>
                            @error('short') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label">Detail / overview <span class="text-muted fw-normal">(service page)</span></label>
                            <textarea name="intro" rows="6" class="form-control @error('intro') is-invalid @enderror">{{ old('intro', $service->intro) }}</textarea>
                            @error('intro') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">What we offer</div>
                <div class="card-body">
                    <div class="row g-3">
                        @include('admin.components.field', [
                            'f' => ['name' => 'offerings', 'label' => 'Offer list', 'type' => 'repeater', 'add' => 'Add offering', 'fields' => [
                                ['name' => 'text', 'label' => 'Offering', 'type' => 'text', 'col' => 12],
                            ]],
                            'name' => 'offerings', 'value' => $offerings,
                        ])
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">Key benefits</div>
                <div class="card-body">
                    <div class="row g-3">
                        @include('admin.components.field', [
                            'f' => ['name' => 'benefits', 'label' => 'Benefits', 'type' => 'repeater', 'add' => 'Add benefit', 'max' => 8, 'fields' => [
                                ['name' => 'title', 'label' => 'Title', 'type' => 'text', 'col' => 4],
                                ['name' => 'text', 'label' => 'Text', 'type' => 'text', 'col' => 8],
                            ]],
                            'name' => 'benefits', 'value' => $benefits,
                        ])
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">Applications <span class="text-muted fw-normal">(tags shown at the bottom of the service page)</span></div>
                <div class="card-body">
                    <select name="applications[]" class="js-tags" multiple data-placeholder="Type an application and press Enter">
                        @foreach ($applications as $a)
                            <option value="{{ $a }}" selected>{{ $a }}</option>
                        @endforeach
                    </select>
                    <div class="form-hint">Press Enter or comma after each tag, e.g. “Hospitals”, “Data centres”.</div>
                </div>
            </div>
        </div>

        {{-- ---------- Side column ---------- --}}
        <div class="col-xl-4">
            <div class="card mb-3">
                <div class="card-header">Publish</div>
                <div class="card-body">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="is_active" @checked(old('is_active', $service->is_active))>
                        <label class="form-check-label" for="is_active">Visible on website</label>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary flex-grow-1"><i class="bi bi-check2"></i> {{ $service->exists ? 'Save changes' : 'Create service' }}</button>
                        <a href="{{ route('admin.services.index') }}" class="btn btn-light border">Cancel</a>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">Tag / category &amp; icon</div>
                <div class="card-body">
                    <label class="form-label">Tag</label>
                    <div class="d-flex gap-2 mb-3">
                        <div class="flex-grow-1">
                            <select name="service_category_id" id="category-select" class="js-select">
                                <option value="">— No tag —</option>
                                @foreach ($categories as $c)
                                    <option value="{{ $c->id }}" @selected((string) old('service_category_id', $service->service_category_id) === (string) $c->id)>{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#tag-modal" title="Create a new tag"><i class="bi bi-plus-lg"></i></button>
                    </div>
                    @error('service_category_id') <div class="text-danger small mb-2">{{ $message }}</div> @enderror

                    <label class="form-label">Icon</label>
                    <select name="icon" class="js-select">
                        @foreach ($icons as $ic)
                            <option value="{{ $ic }}" @selected(old('icon', $service->icon) === $ic)>{{ $ic }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">Image</div>
                <div class="card-body">
                    <div class="row">
                        @include('admin.components.field', [
                            'f' => ['name' => 'image', 'label' => 'Service image', 'type' => 'image', 'required_on_create' => true, 'hint' => 'Landscape image, at least 1200×800.'],
                            'name' => 'image', 'value' => $service->image, 'noOld' => true,
                        ])
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">SEO</div>
                <div class="card-body">
                    <div class="mb-3 field">
                        <label class="form-label">Meta title <span class="char-count"></span></label>
                        <input type="text" name="meta_title" value="{{ old('meta_title', $service->meta_title) }}" class="form-control" data-counter="60">
                    </div>
                    <div class="field">
                        <label class="form-label">Meta description <span class="char-count"></span></label>
                        <textarea name="meta_description" rows="3" class="form-control" data-counter="160">{{ old('meta_description', $service->meta_description) }}</textarea>
                        <div class="form-hint">Leave blank to use the short description.</div>
                    </div>
                    <div class="mt-3">
                        @include('admin.components.serp', ['cfg' => ['titleInput' => '[name=meta_title]', 'descInput' => '[name=meta_description]', 'fallbackTitleInput' => '[name=title]', 'fallbackDescInput' => '[name=short]', 'slugInput' => '[name=slug]', 'prefix' => '/services/']])
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

{{-- New-tag modal: creates the tag by AJAX and selects it in the dropdown --}}
<div class="modal fade" id="tag-modal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="tag-form" autocomplete="off">
            <div class="modal-header">
                <h5 class="modal-title fs-6 fw-bold">New tag</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Tag name</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Electrical" required>
                    <div class="invalid-feedback"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Section heading <span class="text-muted fw-normal">(Services page)</span></label>
                    <input type="text" name="heading" class="form-control" placeholder="e.g. Electrical equipment & installation">
                </div>
                <div>
                    <label class="form-label">Section sub text</label>
                    <textarea name="description" rows="2" class="form-control"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary">Create tag</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const modalEl = document.getElementById('tag-modal');
    document.body.appendChild(modalEl);
    const form = document.getElementById('tag-form');
    const select = document.getElementById('category-select').tomselect;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const nameInput = form.elements.name;
        nameInput.classList.remove('is-invalid');
        const body = new FormData(form);
        body.append('is_active', '1');
        const res = await fetch(@json(route('admin.crud.store', 'categories')), {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, Accept: 'application/json' },
            body,
        });
        const json = await res.json();
        if (!res.ok) {
            nameInput.classList.add('is-invalid');
            nameInput.nextElementSibling.textContent = (json.errors && json.errors.name && json.errors.name[0]) || 'Could not create the tag.';
            return;
        }
        select.addOption({ value: String(json.id), text: json.name });
        select.setValue(String(json.id));
        form.reset();
        bootstrap.Modal.getInstance(modalEl).hide();
        window.toast('success', 'Tag created');
    });
})();
</script>
@endpush
