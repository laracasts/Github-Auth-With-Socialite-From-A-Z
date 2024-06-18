<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules;

class Register
{
    public function handle(array $attributes)
    {
        $validator = Validator::make($attributes, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => [new Rules\RequiredIf(! isset($attributes['github_id'])), 'confirmed', Rules\Password::defaults()],
            'github_id' => ['max:255'],
            'github_token' => [new Rules\RequiredIf(isset($attributes['github_id'])), 'max:255'],
        ]);

        if ($validator->fails()) {
            return redirect(route('register'))->withErrors($validator);
        }

        $user = User::create([
            'name' => $attributes['name'],
            'email' => $attributes['email'],
            'password' => $attributes['password'] ?? null,
            'github_id' => $attributes['github_id'] ?? null,
            'github_token' => $attributes['github_token'] ?? null,
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
