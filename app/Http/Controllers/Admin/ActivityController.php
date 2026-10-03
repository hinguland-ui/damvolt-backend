<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Support\Activity;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function index(Request $request)
    {
        Activity::prune();                                   // never more than the admin's "maximum logs"

        $action = (string) $request->query('action', '');
        $q = trim((string) $request->query('q', ''));
        $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_substr($q, 0, 100)).'%';

        $logs = ActivityLog::query()
            ->when($action !== '', fn ($query) => $query->where('action', $action))
            ->when($q !== '', function ($query) use ($like) {
                $query->where(function ($w) use ($like) {
                    foreach (['description', 'actor', 'ip'] as $column) {
                        $w->orWhereRaw("{$column} LIKE ? ESCAPE '!'", [$like]);
                    }
                });
            })
            ->latest('created_at')->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.activity.index', [
            'logs' => $logs,
            'q' => $q,
            'action' => $action,
            'actions' => ActivityLog::query()->distinct()->orderBy('action')->pluck('action'),
            'maxLogs' => Activity::maxLogs(),
        ]);
    }

    /** Bulk delete: the ticked rows of the table. */
    public function destroy(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer'],
        ]);

        $deleted = ActivityLog::whereIn('id', $data['ids'])->delete();
        Activity::log('delete', "Deleted {$deleted} activity log ".($deleted === 1 ? 'entry' : 'entries'));

        return back()->with('success', "{$deleted} log ".($deleted === 1 ? 'entry' : 'entries').' deleted.');
    }
}
