<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Register;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class RegisteredUserViaGithubController extends Controller
{
    public function create()
    {
        return Socialite::driver('github')->redirect();
    }

    public function store(Register $register)
    {
        $githubUser = Socialite::driver('github')->user();

        if ($user = Auth::user()) {
            return $this->login($user, $githubUser);
        }

        if ($user = User::where(['github_id' => $githubUser->getId()])->first()) {
            return $this->login($user, $githubUser);
        }

        if (User::where(['email' => $githubUser->getEmail(), 'github_id' => null])->exists()) {
            return redirect(route('register'))->withErrors([
                'email' => 'An existing account for this email already exists. Please login and visit your profile settings to add support for Github authentication.',
            ]);
        }

        return $register->handle([
            'name' => $githubUser->getName(),
            'email' => $githubUser->getEmail(),
            'github_id' => $githubUser->getId(),
            'github_token' => $githubUser->token,
        ]);
    }

    public function login(User $user, \Laravel\Socialite\Contracts\User $githubUser): RedirectResponse
    {
        $user->update([
            'github_id' => $githubUser->getId(),
            'github_token' => $githubUser->token,
        ]);

        Auth::login($user);

        return redirect(route('dashboard'));
    }
}
