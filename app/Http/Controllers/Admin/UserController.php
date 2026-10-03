<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Activity;
use Illuminate\Http\Request;

/**
 * Admin accounts. They are created outside the panel, so here an admin can only see them and
 * change a password: no adding, no deleting, and name / email / role / status are locked.
 */
class UserController extends Controller
{
    public function index()
    {
        return view('admin.users.index', ['users' => User::orderBy('id')->get()]);
    }

    public function edit(User $user)
    {
        return view('admin.users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        // Only the password is read from the request — nothing else can be changed from here.
        $data = $request->validate(['password' => ['required', 'string', 'min:8', 'max:200', 'confirmed']]);

        $user->forceFill(['password' => $data['password']])->save();   // hashed by the model cast
        Activity::log('update', 'Changed password of: '.$user->email);

        return redirect()->route('admin.users.index')->with('success', 'Password changed for '.$user->email.'.');
    }
}
