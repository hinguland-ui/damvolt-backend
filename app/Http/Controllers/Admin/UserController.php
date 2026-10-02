<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Activity;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        return view('admin.users.index', ['users' => User::latest()->get()]);
    }

    public function create()
    {
        return view('admin.users.create', ['user' => new User(['is_active' => true, 'role' => 'user'])]);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules() + [
            'password' => ['required', 'min:8', 'confirmed'],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        $created = User::create($data);
        Activity::log('create', 'Added user: '.$created->email);

        return redirect()->route('admin.users.index')->with('success', 'User created successfully.');
    }

    public function edit(User $user)
    {
        return view('admin.users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate($this->rules($user) + [
            'password' => ['nullable', 'min:8', 'confirmed'],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        if ($user->is(auth()->user()) && ($data['role'] !== 'admin' || ! $data['is_active'])) {
            return back()->withInput()->with('error', 'You cannot remove your own admin access or deactivate your own account.');
        }

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);
        Activity::log('update', 'Edited user: '.$user->email);

        return redirect()->route('admin.users.index')->with('success', 'User updated successfully.');
    }

    public function destroy(User $user)
    {
        if ($user->is(auth()->user())) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        Activity::log('delete', 'Deleted user: '.$user->email);
        $user->delete();

        return back()->with('success', 'User deleted.');
    }

    private function rules(?User $user = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users')->ignore($user)],
            'role' => ['required', Rule::in(['admin', 'user'])],
        ];
    }
}
