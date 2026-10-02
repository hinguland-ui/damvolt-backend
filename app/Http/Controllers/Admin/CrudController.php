<?php

namespace App\Http\Controllers\Admin;

use App\Admin\Cruds;
use App\Admin\FormSupport;
use App\Http\Controllers\Controller;
use App\Support\Activity;
use App\Support\Content;
use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** One controller behind every list-with-modal manager defined in App\Admin\Cruds. */
class CrudController extends Controller
{
    private function config(string $resource): array
    {
        return Cruds::get($resource) ?? abort(404);
    }

    /** Standalone page for a manager (e.g. FAQs, Tags). */
    public function index(string $resource)
    {
        $cfg = $this->config($resource);

        return view('admin.crud.page', ['key' => $resource, 'cfg' => $cfg]);
    }

    public function store(Request $request, string $resource)
    {
        $cfg = $this->config($resource);
        $model = $cfg['model'];

        $request->validate(FormSupport::rules($cfg['fields'], creating: true));

        $data = FormSupport::collect($request, $cfg['fields']);
        $data['sort_order'] = ((int) $model::max('sort_order')) + 1;
        $item = $model::create($data);
        Activity::log('create', 'Added '.$cfg['singular'].': '.Str::limit((string) $item->{$cfg['primary']}, 70));

        if ($request->expectsJson()) {
            return response()->json(['id' => $item->id, 'name' => $item->{$cfg['primary']}], 201);
        }

        return $this->back($request, ucfirst($cfg['singular']).' added.');
    }

    public function update(Request $request, string $resource, int $id)
    {
        $cfg = $this->config($resource);
        $item = $cfg['model']::findOrFail($id);

        $request->validate(FormSupport::rules($cfg['fields'], ignoreId: $id));
        $item->update(FormSupport::collect($request, $cfg['fields'], $item->getAttributes()));
        Activity::log('update', 'Edited '.$cfg['singular'].': '.Str::limit((string) $item->{$cfg['primary']}, 70));

        return $this->back($request, ucfirst($cfg['singular']).' updated.');
    }

    public function destroy(Request $request, string $resource, int $id)
    {
        $cfg = $this->config($resource);
        $item = $cfg['model']::findOrFail($id);

        foreach ($cfg['fields'] as $f) {
            if ($f['type'] === 'image') {
                Media::delete($item->{$f['name']});
            }
        }
        Activity::log('delete', 'Deleted '.$cfg['singular'].': '.Str::limit((string) $item->{$cfg['primary']}, 70));
        $item->delete();

        return $this->back($request, ucfirst($cfg['singular']).' deleted.');
    }

    /** Drag & drop: receives the ids in their new order. */
    public function reorder(Request $request, string $resource)
    {
        $cfg = $this->config($resource);
        $ids = array_map('intval', (array) $request->input('ids', []));

        foreach ($ids as $position => $id) {
            $cfg['model']::whereKey($id)->update(['sort_order' => $position]);
        }
        Content::flush();
        Activity::log('reorder', 'Re-ordered '.$cfg['title']);

        return response()->json(['ok' => true]);
    }

    private function back(Request $request, string $message)
    {
        // `_return` must point at this same site (compare the parsed host — a plain "starts with" check
        // would accept http://localhost:8000.evil.com/…).
        $return = (string) $request->input('_return');
        $parts = parse_url($return);
        $sameSite = $parts && isset($parts['scheme'], $parts['host'])
            && $request->getSchemeAndHttpHost() === $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');

        return ($sameSite ? redirect()->to($return) : redirect()->route('admin.dashboard'))->with('success', $message);
    }
}
