<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Services\LoginLock;

class AuthController extends Controller
{
    private const MAX_TRIES = 5;       // wrong passwords allowed...
    private const LOCK_SECONDS = 60;   // ...then wait this long
    
        public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        // Too many wrong passwords: the app must wait (1 min, then 2, then 5). See LoginLock.
        $lock = new LoginLock($data['email'], $request->ip());
        if ($seconds = $lock->secondsLeft()) {
            return $this->locked($seconds);
        }

        $user = User::with(['role', 'driver'])
            ->where('email', $data['email'])
            ->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            $left = $lock->failed();

            if ($left === 0) {
                return $this->locked($lock->secondsLeft());
            }

            return response()->json([
                'message' => 'Wrong email or password. You have ' . $left . ' ' . ($left === 1 ? 'try' : 'tries') . ' left.',
                'errors' => ['email' => ['Wrong email or password. You have ' . $left . ' ' . ($left === 1 ? 'try' : 'tries') . ' left.']],
                'tries_left' => $left,
            ], 422);
        }

        $lock->clear(); // correct password: start counting from zero again

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

    // "Wait before trying again", with the seconds so the app can show a countdown.
    private function locked(int $seconds)
    {
        return response()->json([
            'message' => 'Too many wrong tries. Please wait ' . LoginLock::words($seconds) . ' and try again, or call your dispatcher.',
            'retry_after' => $seconds,
        ], 429);
    }

    public function me(Request $request)
    {
        return response()->json(['user' => $this->payload($request->user())]);
    }

        // The driver edits their own contact number and address. Name, email and license
    // stay with the admin, because they are checked against the driver's papers.
    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'contact_no' => ['required', 'string', 'max:20'],
            'address' => ['required', 'string', 'max:255'],
        ]);

        $request->user()->driver->update($data);

        return response()->json(['user' => $this->payload($request->user()->fresh())]);
    }

    // Change password. Other phones logged in to this account are signed out.
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'string', 'current_password:sanctum'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ], [
            'current_password.current_password' => 'Your current password is wrong.',
            'password.different' => 'The new password must be different from the current one.',
        ]);

        $user = $request->user();
        $user->update(['password' => Hash::make($request->input('password'))]);
        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

        return response()->json(['message' => 'Password changed.']);
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
            'contact_no' => $user->driver->contact_no,
            'address' => $user->driver->address,
            'license_no' => $user->driver->license_no,
            'license_expiry' => $user->driver->license_expiry?->toDateString(),
        ];
    }
}
