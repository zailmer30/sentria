<?php

namespace App\Services\Sessions;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ChamberCaptureTokenService
{
    public function issue(string $name = 'chamber-capture'): string
    {
        $email = (string) config('sentria.chamber.machine_email', 'chamber-capture@sentria.local');
        $ability = (string) config('sentria.chamber.token_ability', 'chamber:capture');

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $user = new User;
            $user->forceFill([
                'email' => $email,
                'first_name' => 'Chamber',
                'last_name' => 'Capture',
                'display_name' => 'Chamber Capture',
                'password' => Hash::make(Str::password(48)),
                'is_active' => false,
                'is_seated_member' => false,
                'email_verified_at' => now(),
                'locale' => 'en',
            ])->save();
        }

        $user->tokens()->where('name', $name)->delete();

        return $user->createToken($name, [$ability])->plainTextToken;
    }
}
