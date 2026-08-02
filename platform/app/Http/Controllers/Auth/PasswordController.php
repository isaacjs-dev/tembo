<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $settings = is_array($request->user()->settings)
            ? $request->user()->settings
            : [];
        unset($settings['must_change_password']);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
            'settings' => $settings,
        ]);

        return back()->with('status', 'password-updated');
    }
}
