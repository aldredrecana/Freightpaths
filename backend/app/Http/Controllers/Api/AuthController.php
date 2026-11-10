<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::with(['role', 'driver'])
            ->where('email', $data['email'])
            ->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'Wrong email or password. Please try again, or call your dispatcher.',
            ]);
        }

        // The phone app is for drivers only. Staff use the website.
        if (! $user->hasRole('driver') || ! $user->driver) {
            throw ValidationException::withMessages([
                'email' => 'This app is for drivers only. Staff please use the website.',
            ]);
        }

        $token = $user
            ->createToken($data['device_name'] ?? 'driver-phone')
            ->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->payload($user),
        ]);
    }

    public function me(Request $request)
    {
        return response()->json(['user' => $this->payload($request->user())]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    private function payload(User $user): array
    {
        $user->loadMissing('driver');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'driver_id' => $user->driver->id,
            'driver_status' => $user->driver->status,
        ];
    }
}