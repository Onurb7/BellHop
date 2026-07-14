<?php

namespace App\Http\Controllers;

use App\Http\Requests\PasswordUpdateRequest;
use Illuminate\Http\RedirectResponse;

class PasswordController extends Controller
{
    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->validated('password'),
        ]);

        return back()->with('success', 'Password updated.');
    }
}
