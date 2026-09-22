<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('a user can set their nickname and photo', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => $user->name,
        'email' => $user->email,
        'nickname' => 'Ace',
        'photo' => UploadedFile::fake()->image('me.png'),
    ])->assertSessionHasNoErrors();

    $user->refresh();
    expect($user->nickname)->toBe('Ace')->and($user->photo_path)->not->toBeNull();
    Storage::disk('public')->assertExists($user->photo_path);
});

test('a user can remove their photo', function () {
    Storage::fake('public');
    $user = User::factory()->create(['photo_path' => UploadedFile::fake()->image('me.png')->store('user-photos', 'public')]);

    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => $user->name,
        'email' => $user->email,
        'remove_photo' => true,
    ]);

    expect($user->fresh()->photo_path)->toBeNull();
});

test('the profile endpoint cannot change the active or admin flags', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => $user->name,
        'email' => $user->email,
        'is_active' => true,
        'is_admin' => true,
    ]);

    expect($user->fresh())->is_active->toBeFalse()->is_admin->toBeFalse();
});
