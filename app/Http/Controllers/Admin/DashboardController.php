<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total' => User::count(),
            'active' => User::where('is_active', true)->count(),
            'admins' => User::where('role', 'admin')->count(),
            'inactive' => User::where('is_active', false)->count(),
        ];

        // Users registered per month (current year)
        $monthly = array_fill(1, 12, 0);
        User::whereYear('created_at', now()->year)->get(['created_at'])
            ->each(function ($u) use (&$monthly) {
                $monthly[$u->created_at->month]++;
            });

        $roles = [
            'Admin' => $stats['admins'],
            'User' => $stats['total'] - $stats['admins'],
        ];

        $latest = User::latest()->take(6)->get();

        return view('admin.dashboard', compact('stats', 'monthly', 'roles', 'latest'));
    }
}
