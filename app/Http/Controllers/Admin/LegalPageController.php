<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LegalPage;
use App\Support\Activity;
use App\Support\Content;
use App\Support\HtmlSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LegalPageController extends Controller
{
    public function index()
    {
        return view('admin.legal.index', ['pages' => LegalPage::orderBy('sort_order')->orderBy('id')->get()]);
    }

    public function create()
    {
        return view('admin.legal.form', ['page' => new LegalPage(['is_active' => true])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['sort_order'] = ((int) LegalPage::max('sort_order')) + 1;
        $page = LegalPage::create($data);
        Activity::log('create', 'Added legal page: '.$page->title);

        return redirect()->route('admin.legal.edit', $page)->with('success', 'Page created.');
    }

    public function edit(LegalPage $legal)
    {
        return view('admin.legal.form', ['page' => $legal]);
    }

    public function update(Request $request, LegalPage $legal)
    {
        $legal->update($this->validated($request, $legal));
        Activity::log('update', 'Edited legal page: '.$legal->title);

        return redirect()->route('admin.legal.edit', $legal)->with('success', 'Page saved.');
    }

    public function destroy(LegalPage $legal)
    {
        Activity::log('delete', 'Deleted legal page: '.$legal->title);
        $legal->delete();

        return redirect()->route('admin.legal.index')->with('success', 'Page deleted.');
    }

    public function reorder(Request $request)
    {
        foreach (array_map('intval', (array) $request->input('ids', [])) as $pos => $id) {
            LegalPage::whereKey($id)->update(['sort_order' => $pos]);
        }
        Content::flush();
        Activity::log('reorder', 'Re-ordered legal pages');

        return response()->json(['ok' => true]);
    }

    private function validated(Request $request, ?LegalPage $page = null): array
    {
        $request->merge(['slug' => Str::slug($request->input('slug') ?: $request->input('title', ''))]);

        $v = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'string', 'max:150', Rule::notIn(['about', 'services', 'industries', 'contact', 'careers', 'faq']), Rule::unique('legal_pages', 'slug')->ignore($page)],
            'content' => ['nullable', 'string'],
            'meta_description' => ['nullable', 'string', 'max:320'],
        ]);
        $v['content'] = HtmlSanitizer::clean($v['content'] ?? '');
        $v['is_active'] = $request->boolean('is_active');

        return $v;
    }
}
