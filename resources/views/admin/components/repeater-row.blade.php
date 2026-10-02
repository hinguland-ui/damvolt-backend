{{-- One row of a repeater. Vars: $f (repeater field), $name, $idx, $row --}}
<div class="rep-row">
    <span class="drag-handle" title="Drag to re-order"><i class="bi bi-grip-vertical"></i></span>
    <div class="rep-fields">
        <div class="row g-2">
            @foreach ($f['fields'] as $sf)
                @include('admin.components.field', [
                    'f' => $sf,
                    'name' => $name . '[' . $idx . '][' . $sf['name'] . ']',
                    'value' => $row[$sf['name']] ?? null,
                    'noOld' => ! empty($blank),
                ])
            @endforeach
        </div>
    </div>
    <button type="button" class="btn btn-sm btn-light border text-danger rep-remove js-rep-remove" title="Remove"><i class="bi bi-trash"></i></button>
</div>
