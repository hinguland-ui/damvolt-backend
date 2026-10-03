<?php

namespace App\Http\Controllers\Admin;

use App\Admin\FormSupport;
use App\Admin\Sections;
use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Support\Activity;
use App\Support\Content;
use App\Support\HtmlSanitizer;
use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ServiceController extends Controller
{
    public function index()
    {
        return view('admin.services.index', [
            'services' => Service::with('category')->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.services.form', $this->formData(new Service(['is_active' => true, 'icon' => 'Zap'])));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['sort_order'] = ((int) Service::max('sort_order')) + 1;
        if ($request->hasFile('image')) {
            $data['image'] = Media::store($request->file('image'), 'uploads/services');
        }

        $created = Service::create($data);
        Activity::log('create', 'Added service: '.$created->title);

        return redirect()->route('admin.services.index')->with('success', 'Service created.');
    }

    public function edit(Service $service)
    {
        return view('admin.services.form', $this->formData($service));
    }

    public function update(Request $request, Service $service)
    {
        $data = $this->validated($request, $service);

        if ($request->hasFile('image')) {
            Media::delete($service->image);
            $data['image'] = Media::store($request->file('image'), 'uploads/services');
        }

        $service->update($data);
        Activity::log('update', 'Edited service: '.$service->title);

        return redirect()->route('admin.services.index')->with('success', 'Service updated.');
    }

    public function destroy(Service $service)
    {
        Media::delete($service->image);
        Activity::log('delete', 'Deleted service: '.$service->title);
        $service->delete();

        return back()->with('success', 'Service deleted.');
    }

    private function formData(Service $service): array
    {
        return [
            'service' => $service,
            'categories' => ServiceCategory::orderBy('sort_order')->get(['id', 'name']),
            'icons' => Sections::icons(),
        ];
    }

    public function reorder(Request $request)
    {
        foreach (array_map('intval', (array) $request->input('ids', [])) as $pos => $id) {
            Service::whereKey($id)->update(['sort_order' => $pos]);
        }
        Content::flush();
        Activity::log('reorder', 'Re-ordered services');

        return response()->json(['ok' => true]);
    }

    private function validated(Request $request, ?Service $service = null): array
    {
        $request->merge(['slug' => Str::slug($request->input('slug') ?: $request->input('title', ''))]);
        FormSupport::prune($request, [
            ['name' => 'offerings', 'type' => 'repeater'],
            ['name' => 'benefits', 'type' => 'repeater'],
        ]);

        $v = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['required', 'string', 'max:200', Rule::unique('services', 'slug')->ignore($service)],
            'service_category_id' => ['nullable', 'exists:service_categories,id'],
            'icon' => ['required', 'string', 'max:60'],
            'short' => ['nullable', 'string', 'max:400'],
            'intro' => ['nullable', 'string', 'max:30000'],
            'offerings' => ['nullable', 'array'],
            'offerings.*.text' => ['nullable', 'string', 'max:300'],
            'benefits' => ['nullable', 'array'],
            'benefits.*.title' => ['nullable', 'string', 'max:120'],
            'benefits.*.text' => ['nullable', 'string', 'max:400'],
            'applications' => ['nullable', 'array'],
            'applications.*' => ['string', 'max:120'],
            'image' => [$service?->image ? 'nullable' : 'required', 'image', 'max:6144'],
            'meta_title' => ['nullable', 'string', 'max:160'],
            'meta_description' => ['nullable', 'string', 'max:320'],
        ]);

        return [
            'title' => $v['title'],
            'slug' => $v['slug'],
            'service_category_id' => $v['service_category_id'] ?? null,
            'icon' => $v['icon'],
            'short' => $v['short'] ?? null,
            'intro' => HtmlSanitizer::clean($v['intro'] ?? '') ?: null,   // rich-text editor output, allow-listed tags only
            'offerings' => array_values(array_filter(array_column($v['offerings'] ?? [], 'text'))),
            'benefits' => array_values(array_filter($v['benefits'] ?? [], fn ($b) => ! empty($b['title']) || ! empty($b['text']))),
            'applications' => array_values($v['applications'] ?? []),
            'meta_title' => $v['meta_title'] ?? null,
            'meta_description' => $v['meta_description'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
