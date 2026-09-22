<?php

use App\Models\User;

test('password reset routes are not available', function () {
    $this->get('/forgot-password')->assertNotFound();
    $this->post('/forgot-password', ['email' => 'test@example.com'])->assertNotFound();
    $this->get('/reset-password/some-token')->assertNotFound();
    $this->post('/reset-password')->assertNotFound();
});

test('email verification routes are not available', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/email/verify')->assertNotFound();
    $this->actingAs($user)->post('/email/verification-notification')->assertNotFound();
});

test('two factor authentication routes are not available', function () {
    $this->get('/two-factor-challenge')->assertNotFound();
    $this->post('/two-factor-challenge')->assertNotFound();

    $user = User::factory()->create();

    $this->actingAs($user)->post('/user/two-factor-authentication')->assertNotFound();
    $this->actingAs($user)->get('/user/two-factor-qr-code')->assertNotFound();
    $this->actingAs($user)->get('/user/two-factor-recovery-codes')->assertNotFound();
});
