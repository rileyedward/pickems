<?php

use App\Models\User;
use App\Models\Week;
use Illuminate\Support\Facades\RateLimiter;

test('the registration screen can be rendered', function () {
    $this->get(route('register'))->assertOk();
});

test('new users can register and start inactive and non-admin', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('home', absolute: false));

    expect(User::firstWhere('email', 'test@example.com'))
        ->is_active->toBeFalse()
        ->is_admin->toBeFalse();
});

test('registration cannot set the active or admin flags', function () {
    $this->post(route('register.store'), [
        'name' => 'Sneaky',
        'email' => 'sneaky@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'is_active' => true,
        'is_admin' => true,
    ]);

    expect(User::firstWhere('email', 'sneaky@example.com'))
        ->is_active->toBeFalse()
        ->is_admin->toBeFalse();
});

test('the registration passcode activates the new user and enters them into the open week', function () {
    config(['app.registration_passcode' => 'go-birds']);
    $week = Week::factory()->open()->create();

    $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'passcode' => 'go-birds',
    ])->assertSessionHasNoErrors();

    $user = User::firstWhere('email', 'test@example.com');

    expect($user)
        ->is_active->toBeTrue()
        ->is_admin->toBeFalse();
    expect($week->entries()->where('user_id', $user->id)->exists())->toBeTrue();
});

test('a wrong registration passcode is rejected', function () {
    config(['app.registration_passcode' => 'go-birds']);

    $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'passcode' => 'wrong',
    ])->assertSessionHasErrors('passcode');

    $this->assertGuest();
});

test('no passcode works when none is configured', function () {
    config(['app.registration_passcode' => null]);

    $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'passcode' => '',
    ])->assertSessionHasNoErrors();

    expect(User::firstWhere('email', 'test@example.com'))->is_active->toBeFalse();

    auth()->logout();

    $this->post(route('register.store'), [
        'name' => 'Other User',
        'email' => 'other@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'passcode' => 'anything',
    ])->assertSessionHasErrors('passcode');
});

test('the password must be confirmed', function () {
    $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'different',
    ])->assertSessionHasErrors('password');

    $this->assertGuest();
});

test('registration is rate limited', function () {
    foreach (range(1, 5) as $attempt) {
        RateLimiter::hit(md5('register'.'127.0.0.1'));
    }

    $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertTooManyRequests();
});
