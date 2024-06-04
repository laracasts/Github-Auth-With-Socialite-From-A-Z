<?php

use App\Models\User;
use Laravel\Socialite\Facades\Socialite;

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register', function () {
    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticated();
});

describe('Validation', function () {
    test('requires a name', function () {
        $attributes = User::factory()->raw(['name' => null]);

        $this->post('/register', $attributes)->assertInvalid();
    });

    test('requires a valid email', function () {
        $attributes = User::factory()->raw(['email' => 'not-an-email']);

        $this->post('/register', $attributes)->assertInvalid();
    });

    test('requires a password', function () {
        $attributes = User::factory()->raw(['password' => null]);

        $this->post('/register', $attributes)->assertInvalid();
    });

    test('password is optional if github_id is provided', function () {
        $attributes = User::factory()->raw(['github_id' => 'fooid', 'github_token' => 'token']);

        unset($attributes['password']);

        $this->post('/register', $attributes)->assertValid();
    });

    test('requires github_token if github_id is provided', function () {
        $attributes = User::factory()->raw(['github_id' => 'fooid']);

        $this->post('/register', $attributes)->assertInvalid(['github_token']);
    });
});

describe('Register Using Github', function () {
    test('redirect to Github for authorization', function () {
        Socialite::shouldReceive('driver->redirect')->once();

        $this->get('/auth/redirect');

        expect(true)->toBeTrue();
    });

    test('register successfully', function () {
        $fakeGithubUser = (new \Laravel\Socialite\Two\User)->map(attributes: [
            'id' => 'id123',
            'name' => 'Test User',
            'email' => 'test@example.com',
            'token' => 'token123',
        ]);

        Socialite::shouldReceive('driver->user')->once()->andReturn($fakeGithubUser);

        $this->get('auth/callback')->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
    });

    test('add Github credentials to existing account.', function () {
        // given I have a user
        $user = User::factory()->create();

        // and that user is signed in
        $this->actingAs($user);

        $githubUser = fakeGithubUser();

        // if they grant authorization to Github
        $this->get('auth/callback');

        // their user record should be updated with github_id credentials.
        expect($user->refresh()->github_id)->toBe($githubUser->getId());
        expect($user->github_token)->toBe($githubUser->token);
    });

    test('login existing account', function () {
        $user = User::factory()->create(['github_id' => 'id123', 'github_token' => 'oldtoken123']);

        $githubUser = fakeGithubUser(['github_token' => 'newtoken123']);

        $this->get('auth/callback')->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();

        expect($user->refresh()->github_id)->toBe($githubUser->getId());
        expect($user->refresh()->github_token)->toBe($githubUser->token);
    });

    test('existing account with same email address requires login.', function () {
        User::factory()->create(['email' => 'test@example.com', 'github_id' => null]);

        fakeGithubUser(['email' => 'test@example.com']);

        $this->get('auth/callback')
            ->assertInvalid(['email' => 'An existing account for this email already exists. Please login and visit your profile settings to add support for Github authentication.'])
            ->assertRedirect(route('register'));
    });
});

function fakeGithubUser(array $attributes = []): \Laravel\Socialite\Two\User
{
    $fakeGithubUser = (new \Laravel\Socialite\Two\User)->map(attributes: array_merge([
        'id' => 'id123',
        'name' => 'Test User',
        'email' => 'test@example.com',
        'token' => 'newtoken123',
    ], $attributes));

    Socialite::shouldReceive('driver->user')->once()->andReturn($fakeGithubUser);

    return $fakeGithubUser;
}
