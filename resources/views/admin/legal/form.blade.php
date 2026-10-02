@extends('admin.layouts.app')
@section('title', $page->exists ? $page->title : 'Add legal page')

@section('content')
<form method="POST" action="{{ $page->exists ? route('admin.legal.update', $page) : route('admin.legal.store') }}">
    @csrf
    @if ($page->exists) @method('PUT') @endif

    <div class="row g-3">
        <div class="col-xl-9">
            <div class="card">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label">Page title</label>
                            <input type="text" id="title" name="title" value="{{ old('title', $page->title) }}" class="form-control @error('title') is-invalid @enderror" required>
                            @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">URL slug</label>
                            <div class="input-group slug-input">
                                <span class="input-group-text">/</span>
                                <input type="text" name="slug" value="{{ old('slug', $page->slug) }}" data-slug-source="#title" class="form-control @error('slug') is-invalid @enderror" required>
                            </div>
                            @error('slug') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label">Content</label>
                            <input type="hidden" id="content" name="content" value="{{ old('content', $page->content) }}">
                            <div class="js-quill" data-input="#content"></div>
                            @error('content') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            <div class="form-hint">Use “Heading” for section titles (e.g. Information We Collect), lists for bullet points.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3">
            <div class="card mb-3">
                <div class="card-header">Publish</div>
                <div class="card-body">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="is_active" @checked(old('is_active', $page->is_active))>
                        <label class="form-check-label" for="is_active">Visible on website</label>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary flex-grow-1"><i class="bi bi-check2"></i> Save</button>
                        <a href="{{ route('admin.legal.index') }}" class="btn btn-light border">Back</a>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-header">SEO</div>
                <div class="card-body field">
                    <label class="form-label">Meta description <span class="char-count"></span></label>
                    <textarea name="meta_description" rows="4" class="form-control" data-counter="160">{{ old('meta_description', $page->meta_description) }}</textarea>
                    <div class="mt-3">
                        @include('admin.components.serp', ['cfg' => ['descInput' => '[name=meta_description]', 'fallbackTitleInput' => '[name=title]', 'slugInput' => '[name=slug]', 'prefix' => '/']])
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection
