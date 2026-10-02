<?php

namespace App\Http\Controllers\Admin;

use App\Admin\Cruds;
use App\Admin\FormSupport;
use App\Admin\Sections;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

/** Renders the tabbed pages (Home Page, Site Settings) and saves each tab on its own. */
class SectionController extends Controller
{
    public function show(string $group)
    {
        $schema = Sections::group($group) ?? abort(404);

        $data = [];
        foreach ($schema['tabs'] as $tab) {
            if (isset($tab['store'])) {
                $data[$tab['key']] = Setting::section($tab['store']);

                // Saved credentials are shown in the form (masked, with an eye button) so the admin can read them back.
                foreach ($tab['fields'] ?? [] as $f) {
                    if ($f['type'] === 'password' && ! empty($data[$tab['key']][$f['name']])) {
                        try {
                            $data[$tab['key']][$f['name']] = Crypt::decryptString($data[$tab['key']][$f['name']]);
                        } catch (\Throwable) {
                            $data[$tab['key']][$f['name']] = '';
                        }
                    }
                }
            }
        }

        return view('admin.sections.show', [
            'group' => $group,
            'schema' => $schema,
            'data' => $data,
            'cruds' => Cruds::all(),
        ]);
    }

    public function update(Request $request, string $group, string $section)
    {
        $tab = Sections::tab($group, $section) ?? abort(404);
        abort_unless(isset($tab['fields']), 404);

        $fields = $tab['fields'];
        FormSupport::prune($request, $fields);
        $request->validate(FormSupport::rules($fields));

        $existing = Setting::section($tab['store']);
        Setting::put($tab['store'], FormSupport::collect($request, $fields, $existing));
        Activity::log('update', "Saved “{$tab['title']}” in ".($group === 'home' ? 'Home Page' : ($group === 'seo' ? 'Page SEO' : 'Site Settings')));

        return redirect()->to(route('admin.sections.show', $group).'#tab-'.$section)
            ->with('success', $tab['title'].' saved.');
    }
}
