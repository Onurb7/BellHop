<?php

use App\Models\User;

beforeEach(function () {
    config(['demo.login_enabled' => true]);
});

it('logs in the matching seeded user for each demo role', function (string $role) {
    $user = User::factory()->create(['email' => "demo.{$role}@example.test"]);
    config(["demo.accounts.{$role}.email" => $user->email]);

    $this->post("/login-as/{$role}")->assertRedirect();

    $this->assertAuthenticatedAs($user);
})->with(['admin', 'staff', 'guest']);

it('never exposes super-admin through the demo login route, even though a super-admin account exists', function () {
    User::factory()->create(['email' => 'super.admin@example.test']);

    // config('demo.accounts') structurally has no `super-admin` key — this
    // isn't a runtime check being bypassed, the route has nothing to read.
    expect(config('demo.accounts.super-admin'))->toBeNull();

    $this->post('/login-as/super-admin')->assertNotFound();

    $this->assertGuest();
});

it('404s when the demo login switcher is disabled', function () {
    config(['demo.login_enabled' => false]);
    User::factory()->create(['email' => 'demo.admin@example.test']);
    config(['demo.accounts.admin.email' => 'demo.admin@example.test']);

    $this->post('/login-as/admin')->assertNotFound();
});
