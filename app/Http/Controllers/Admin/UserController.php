<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Weeks\EnrollInOpenWeek;
use App\Concerns\PasswordValidationRules;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Account management: who plays (active), who runs things (admin), and
 * profile fixes for anyone.
 */
class UserController extends Controller
{
    use PasswordValidationRules;

    public function index(): Response
    {
        return Inertia::render('Admin/Users/Index', [
            'users' => User::alphabetical()
                ->withCount(['entries', 'entries as weeks_played' => fn ($query) => $query->whereNotNull('submitted_at')])
                ->get()
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'nickname' => $user->nickname,
                    'email' => $user->email,
                    'display_name' => $user->display_name,
                    'photo' => $user->photo_url,
                    'is_active' => $user->is_active,
                    'is_admin' => $user->is_admin,
                    'entries_count' => (int) $user->getAttribute('entries_count'),
                    'weeks_played' => (int) $user->getAttribute('weeks_played'),
                ]),
        ]);
    }

    /**
     * Create an account for someone who won't register themselves. It starts
     * active.
     */
    public function store(Request $request, EnrollInOpenWeek $enroll): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'nickname' => ['nullable', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => $this->passwordRules(),
            'photo' => ['nullable', 'image', 'max:5120'],
        ]);

        $user = User::create(Arr::except($validated, 'photo'));
        $user->forceFill(['is_active' => true])->save();
        $enroll->handle($user);

        if ($request->hasFile('photo')) {
            $user->replacePhoto($request->file('photo'));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$user->name} added."]);

        return back();
    }

    /**
     * Every field is optional so a switch (active, admin) can patch alone.
     * Activating someone enters them into the week that's taking picks.
     */
    public function update(Request $request, User $user, EnrollInOpenWeek $enroll): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'nickname' => ['sometimes', 'nullable', 'string', 'max:50'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'is_active' => ['sometimes', 'boolean'],
            'is_admin' => ['sometimes', 'boolean'],
            'photo' => ['sometimes', 'nullable', 'image', 'max:5120'],
            'remove_photo' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('is_admin', $validated) && ! $validated['is_admin'] && $user->isLastAdmin()) {
            throw ValidationException::withMessages(['user' => 'There has to be at least one admin. Make someone else an admin first.']);
        }

        $user->fill(Arr::only($validated, ['name', 'nickname', 'email']));
        $user->forceFill(Arr::only($validated, ['is_active', 'is_admin']));
        $activated = $user->isDirty('is_active') && $user->is_active;
        $user->save();

        if ($activated) {
            $enroll->handle($user);
        }

        if ($request->boolean('remove_photo')) {
            $user->removePhoto();
        }

        if ($request->hasFile('photo')) {
            $user->replacePhoto($request->file('photo'));
        }

        return back();
    }

    /**
     * Set a new password for someone who's locked out (there's no email
     * reset).
     */
    public function password(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate(['password' => $this->passwordRules()]);

        $user->update(['password' => $validated['password']]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "New password set for {$user->name}."]);

        return back();
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->entries()->exists()) {
            throw ValidationException::withMessages(['user' => 'This user has played. Deactivate them instead.']);
        }

        if ($user->isLastAdmin()) {
            throw ValidationException::withMessages(['user' => 'You can\'t delete the only admin.']);
        }

        if ($user->is($request->user())) {
            throw ValidationException::withMessages(['user' => 'Delete your own account from your settings.']);
        }

        $user->removePhoto();
        $user->delete();

        return back();
    }
}
