<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Register;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;

class RegisteredUserViaGithubController extends Controller
{
    public function create(): RedirectResponse
    {
        return Socialite::driver('github')->redirect();
    }

    public function store(Register $register): RedirectResponse
    {
        $githubUser = Socialite::driver('github')->user();

        // If the user is signed in, associate the GitHub
        // token with their account.
        if ($user = Auth::user()) {
            return $this->login($user, $githubUser);
        }

        // If, upon registering, the GitHub token already exists in our db,
        // associate those credentials with that account.
        if ($user = User::where(['github_id' => $githubUser->getId()])->first()) {
            return $this->login($user, $githubUser);
        }

        // If we already have an account for that GitHub email address, ask
        // the user to login and try again.
        if (User::where(['email' => $githubUser->getEmail(), 'github_id' => null])->exists()) {
            return redirect(route('register'))->withErrors([
                'email' => 'An account for this email already exists. Please login and visit your settings page to add Github authentication.',
            ]);
        }

        // Otherwise, register them!
        return $register->handle([
            'name' => $githubUser->getName(),
            'email' => $githubUser->getEmail(),
            'github_id' => $githubUser->getId(),
            'github_token' => $githubUser->token,
        ]);
    }

    public function login(User $user, SocialiteUser $githubUser): RedirectResponse
    {
        $user->update([
            'github_id' => $githubUser->getId(),
            'github_token' => $githubUser->token,
        ]);

        Auth::login($user);

        return redirect(route('dashboard'));
    }
}
