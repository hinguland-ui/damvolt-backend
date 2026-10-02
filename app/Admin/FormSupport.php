<?php

namespace App\Admin;

use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

/** Shared validation / saving logic for schema-driven admin forms. */
class FormSupport
{
    /** Fields that hold a link — they are checked so a javascript: / data: URL can never be saved. */
    private const URL_FIELDS = ['btn1_url', 'btn2_url', 'button_url', 'facebook', 'instagram', 'linkedin', 'youtube', 'map', 'map_link'];

    /** Drop completely empty repeater rows before validating, so a blank "Add" row never blocks saving. */
    public static function prune(Request $request, array $fields): void
    {
        foreach ($fields as $f) {
            if ($f['type'] !== 'repeater' || ! $request->has($f['name'])) {
                continue;
            }

            $rows = array_values(array_filter(
                (array) $request->input($f['name']),
                fn ($row) => array_filter((array) $row, fn ($v) => $v !== null && trim((string) $v) !== '')
            ));
            $request->merge([$f['name'] => $rows]);
        }
    }

    /** Laravel validation rules for a list of schema fields. */
    public static function rules(array $fields, bool $creating = false, ?int $ignoreId = null, string $prefix = ''): array
    {
        $rules = [];

        foreach ($fields as $f) {
            $key = $prefix.$f['name'];

            if ($f['type'] === 'repeater') {
                $rules[$key] = 'nullable|array|max:'.($f['max'] ?? 50);
                $rules += self::rules($f['fields'], $creating, null, $key.'.*.');

                continue;
            }

            if ($f['type'] === 'serp') {
                continue;
            }

            if ($f['type'] === 'switch') {
                $rules[$key] = 'nullable|boolean';

                continue;
            }

            $rule = $f['rules'] ?? 'nullable|string|max:255';
            if (in_array($f['name'], self::URL_FIELDS, true) && ! str_contains($rule, 'safe_url')) {
                $rule .= '|safe_url';
            }

            if ($f['type'] === 'image' && $creating && ! empty($f['required_on_create'])) {
                $rule = str_replace('nullable', 'required', $rule);
            }

            if ($ignoreId && str_contains($rule, 'unique:')) {
                $rule = preg_replace('/(unique:[^|]+)/', '$1,'.$ignoreId, $rule);
            }

            $rules[$key] = $rule;
        }

        return $rules;
    }

    /**
     * Turn a validated request into the array to store: handles image upload / removal and
     * tidies repeaters (drops empty rows, re-indexes in the order they were dragged into).
     */
    public static function collect(Request $request, array $fields, array $existing = []): array
    {
        $data = [];

        foreach ($fields as $f) {
            $name = $f['name'];

            if ($f['type'] === 'serp') {
                continue;
            }

            switch ($f['type']) {
                case 'image':
                    $old = $existing[$name] ?? null;
                    if ($request->hasFile($name)) {
                        Media::delete($old);
                        $data[$name] = Media::store($request->file($name));
                    } elseif ($request->boolean("remove_$name")) {
                        Media::delete($old);
                        $data[$name] = null;
                    } else {
                        $data[$name] = $old;
                    }
                    break;

                case 'switch':
                    $data[$name] = $request->boolean($name);
                    break;

                case 'password':
                    // Stored encrypted. The form shows the saved value, so what is submitted is the new value (blank = cleared).
                    $plain = (string) $request->input($name, '');
                    $data[$name] = $plain !== '' ? Crypt::encryptString($plain) : '';
                    break;

                case 'repeater':
                    $rows = [];
                    foreach ((array) $request->input($name, []) as $row) {
                        $row = array_map(fn ($v) => is_string($v) ? trim($v) : $v, (array) $row);
                        if (array_filter($row, fn ($v) => $v !== null && $v !== '')) {
                            $rows[] = $row;
                        }
                    }
                    $data[$name] = $rows;
                    break;

                default:
                    $data[$name] = $request->input($name);
            }
        }

        return $data;
    }
}
