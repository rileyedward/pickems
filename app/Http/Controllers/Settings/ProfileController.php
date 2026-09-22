<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(): Response
    {
        return Inertia::render('settings/Profile');
    }

    /**
     * Update the user's profile information. The active and admin flags are
     * admin-only and can't be changed here.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->update(collect($request->validated())->only(['name', 'nickname', 'email'])->all());

        if ($request->boolean('remove_photo')) {
            $user->removePhoto();
        }

        if ($request->hasFile('photo')) {
            $user->replacePhoto($request->file('photo'));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->isLastAdmin()) {
            throw ValidationException::withMessages(['password' => 'You are the only admin. Make someone else an admin before deleting your account.']);
        }

        Auth::logout();

        $user->removePhoto();
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
