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
        Activity::prune();                                   // what is shown is never older than 7 days

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
            'keepDays' => Activity::KEEP_DAYS,
        ]);
    }
}
