<?php

use App\Models\User;
use App\Models\Week;

test('guests are sent to the login page', function () {
    $week = Week::factory()->create();

    $this->get('/admin')->assertRedirect(route('login'));
    $this->get(route('admin.weeks.show', $week))->assertRedirect(route('login'));
    $this->post(route('admin.weeks.open', $week))->assertRedirect(route('login'));
    $this->get(route('admin.users.index'))->assertRedirect(route('login'));
});

test('non-admins are forbidden', function () {
    $user = User::factory()->active()->create();
    $week = Week::factory()->create();

    $this->actingAs($user)->get('/admin')->assertForbidden();
    $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
    $this->actingAs($user)->post(route('admin.weeks.open', $week))->assertForbidden();
});

test('admins get in', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/admin')->assertRedirect(route('admin.seasons.index'));
    $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
});

test('the shared auth user tells the front end who is an admin', function () {
    $admin = User::factory()->admin()->create(['nickname' => 'Boss']);

    $this->actingAs($admin)->get('/')->assertInertia(fn ($page) => $page
        ->where('auth.user.is_admin', true)
        ->where('auth.user.is_active', true)
        ->where('auth.user.display_name', 'Boss'));
});

test('the last admin cannot remove their own admin flag', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.users.update', $admin), ['is_admin' => false])
        ->assertSessionHasErrors('user');

    expect($admin->fresh()->is_admin)->toBeTrue();
});

test('an admin can step down once someone else is an admin', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.users.update', $admin), ['is_admin' => false])
        ->assertSessionHasNoErrors();

    expect($admin->fresh()->is_admin)->toBeFalse();
});

test('the last admin cannot delete their own account', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertSessionHasErrors('password');

    expect($admin->fresh())->not->toBeNull();
});
