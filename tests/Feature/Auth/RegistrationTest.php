<?php

use App\Models\User;
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
