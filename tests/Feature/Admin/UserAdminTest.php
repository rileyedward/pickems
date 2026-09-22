<?php

use App\Models\Entry;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('the users page lists everyone', function () {
    User::factory()->count(2)->create();

    $this->actingAs($this->admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Users/Index')->has('users', 3));
});

test('an admin can activate and deactivate a user', function () {
    $user = User::factory()->create();

    $this->actingAs($this->admin)->post(route('admin.users.update', $user), ['is_active' => true]);
    expect($user->fresh()->is_active)->toBeTrue();

    $this->actingAs($this->admin)->post(route('admin.users.update', $user), ['is_active' => false]);
    expect($user->fresh()->is_active)->toBeFalse();
});

test('an admin can make another user an admin', function () {
    $user = User::factory()->active()->create();

    $this->actingAs($this->admin)->post(route('admin.users.update', $user), ['is_admin' => true]);

    expect($user->fresh()->is_admin)->toBeTrue();
});

test('an admin can edit a profile and upload a photo', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $this->actingAs($this->admin)->post(route('admin.users.update', $user), [
        'name' => 'Robert Smith',
        'nickname' => 'Bobby',
        'photo' => UploadedFile::fake()->image('face.jpg'),
    ])->assertSessionHasNoErrors();

    $user->refresh();
    expect($user)->name->toBe('Robert Smith')->display_name->toBe('Bobby')
        ->and($user->photo_path)->not->toBeNull();
    Storage::disk('public')->assertExists($user->photo_path);

    $path = $user->photo_path;
    $this->actingAs($this->admin)->post(route('admin.users.update', $user), ['remove_photo' => true]);

    expect($user->fresh()->photo_path)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

test('an admin can set a new password', function () {
    $user = User::factory()->create();

    $this->actingAs($this->admin)->put(route('admin.users.password', $user), [
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertSessionHasNoErrors();

    expect(Hash::check('new-password', $user->fresh()->password))->toBeTrue();
});

test('an admin can create a user who starts active', function () {
    $this->actingAs($this->admin)->post(route('admin.users.store'), [
        'name' => 'Grandpa Joe',
        'email' => 'joe@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasNoErrors();

    expect(User::firstWhere('email', 'joe@example.com'))->is_active->toBeTrue()->is_admin->toBeFalse();
});

test('a user without entries can be deleted', function () {
    $user = User::factory()->create();

    $this->actingAs($this->admin)->delete(route('admin.users.destroy', $user))->assertSessionHasNoErrors();

    expect($user->fresh())->toBeNull();
});

test('a user who has played cannot be deleted', function () {
    $user = User::factory()->active()->create();
    Entry::factory()->for($user)->create();

    $this->actingAs($this->admin)->delete(route('admin.users.destroy', $user))->assertSessionHasErrors('user');

    expect($user->fresh())->not->toBeNull();
});
