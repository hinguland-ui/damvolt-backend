{{--
    One schema-driven form field.
    Vars: $f (field definition), $name (input name, may be like items[3][title]), $value (current value), $noOld (skip old())
--}}
@php
    $type = $f['type'];
    $col = $f['col'] ?? 12;
    $dot = preg_replace('/\[(\w+)\]/', '.$1', $name);          // items[3][title] -> items.3.title
    $val = empty($noOld) ? old($dot, $value ?? null) : ($value ?? null);
    $uid = 'f_' . substr(md5($name . microtime(true) . mt_rand()), 0, 8);
    $err = $errors->first($dot);
@endphp

<div class="col-12 col-md-{{ $col }} field">
    @if ($type === 'serp')
        @include('admin.components.serp', ['cfg' => $f])

    @elseif ($type === 'repeater')
        @php $rows = is_array($val) ? array_values($val) : []; @endphp
        <label class="form-label">{{ $f['label'] }}</label>
        <div class="js-repeater" data-next="1000" data-max="{{ $f['max'] ?? 50 }}">
            <div class="rep-rows">
                @foreach ($rows as $i => $row)
                    @include('admin.components.repeater-row', ['f' => $f, 'name' => $name, 'idx' => $i, 'row' => (array) $row])
                @endforeach
            </div>
            <template>
                @include('admin.components.repeater-row', ['f' => $f, 'name' => $name, 'idx' => '__IDX__', 'row' => [], 'blank' => true])
            </template>
            <button type="button" class="btn btn-outline-primary btn-sm mt-2 js-rep-add"><i class="bi bi-plus-lg"></i> {{ $f['add'] ?? 'Add' }}</button>
        </div>

    @elseif ($type === 'switch')
        <div class="form-check form-switch pt-md-4">
            <input class="form-check-input" type="checkbox" role="switch" id="{{ $uid }}" name="{{ $name }}" value="1" @checked($val ?? ($f['default'] ?? true))>
            <label class="form-check-label" for="{{ $uid }}">{{ $f['label'] }}</label>
        </div>

    @elseif ($type === 'image')
        @php $url = $val ? \App\Support\Media::url($val) : null; @endphp
        <label class="form-label" for="{{ $uid }}">{{ $f['label'] }}</label>
        <div class="img-field">
            <div class="preview {{ ! empty($f['dark']) ? 'dark' : '' }}">
                @if ($url) <img src="{{ $url }}" alt=""> @else <i class="bi bi-image"></i> @endif
            </div>
            <div class="meta">
                <label class="upload-btn" for="{{ $uid }}"><i class="bi bi-upload"></i> {{ $url ? 'Change image' : 'Choose image' }}</label>
                <span class="file-name">No file chosen</span>
                <input type="file" id="{{ $uid }}" name="{{ $name }}" accept="image/*" class="visually-hidden js-img-input @if($err) is-invalid @endif">
                @if ($url && empty($f['required_on_create']))
                    <div class="form-check mt-1">
                        <input class="form-check-input" type="checkbox" name="remove_{{ $name }}" value="1" id="rm_{{ $uid }}">
                        <label class="form-check-label small text-muted" for="rm_{{ $uid }}">Remove image</label>
                    </div>
                @endif
                @if (! empty($f['hint'])) <div class="form-hint">{{ $f['hint'] }}</div> @endif
            </div>
        </div>
        @if ($err) <div class="text-danger small mt-1">{{ $err }}</div> @endif

    @else
        <label class="form-label" for="{{ $uid }}">
            {{ $f['label'] }}
            @if (! empty($f['counter'])) <span class="char-count"></span> @endif
        </label>

        @if ($type === 'textarea')
            <textarea id="{{ $uid }}" name="{{ $name }}" rows="{{ $f['rows'] ?? 3 }}" class="form-control @if($err) is-invalid @endif"
                @if (! empty($f['counter'])) data-counter="{{ $f['counter'] }}" @endif>{{ $val }}</textarea>

        @elseif ($type === 'password')
            <input id="{{ $uid }}" type="password" name="{{ $name }}" value="{{ $val }}" autocomplete="new-password" class="form-control @if($err) is-invalid @endif">

        @elseif ($type === 'select')
            <select id="{{ $uid }}" name="{{ $name }}" class="js-select @if($err) is-invalid @endif">
                @foreach ($f['options'] as $ov => $ol)
                    <option value="{{ $ov }}" @selected((string) $val === (string) $ov)>{{ $ol }}</option>
                @endforeach
            </select>

        @else
            <input id="{{ $uid }}" type="{{ in_array($type, ['number', 'email', 'url']) ? $type : 'text' }}" name="{{ $name }}" value="{{ $val }}"
                class="form-control @if($err) is-invalid @endif" @if (! empty($f['counter'])) data-counter="{{ $f['counter'] }}" @endif>
        @endif

        @if ($err) <div class="invalid-feedback d-block">{{ $err }}</div> @endif
        @if (! empty($f['hint'])) <div class="form-hint">{{ $f['hint'] }}</div> @endif
    @endif
</div>
