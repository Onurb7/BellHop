<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();
        $isGuest = ! $user->hasAnyRole(['staff', 'admin', 'super-admin']);
        $guest = $isGuest ? $user->guest : null;

        return Inertia::render('Profile/Edit', [
            'is_guest' => $isGuest,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $guest?->phone,
            'address' => $guest?->address,
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $user->update([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
        ]);

        // Keeps the linked `guests` row (what staff actually see on a
        // booking) in sync with the account's own profile — created on
        // first save if this guest has never had one yet, so
        // phone/address are captured ahead of the self-service booking
        // flow that will need them later.
        if ($user->hasAnyRole(['staff', 'admin', 'super-admin'])) {
            return back()->with('success', 'Profile updated.');
        }

        $user->guest()->updateOrCreate([], [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
        ]);

        return back()->with('success', 'Profile updated.');
    }
}
