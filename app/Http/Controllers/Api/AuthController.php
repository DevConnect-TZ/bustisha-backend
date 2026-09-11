<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TextifyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // ─── OTP ────────────────────────────────────────────────────────────────

    /**
     * Generate a 5-digit OTP, cache it for 10 minutes, and SMS it to the user.
     */
    public function sendOtp(Request $request)
    {
        $data = $request->validate([
            'phone' => 'required|string|max:20',
        ]);

        $phone = $data['phone'];

        // Rate-limit: one OTP request per phone per 60 seconds
        $rateLimitKey = "otp_rate:{$phone}";
        if (Cache::has($rateLimitKey)) {
            return response()->json(['message' => 'Please wait 60 seconds before requesting another OTP.'], 429);
        }

        $otp = str_pad(random_int(10000, 99999), 5, '0', STR_PAD_LEFT);

        // Store OTP (TTL: 10 minutes)
        Cache::put("otp:{$phone}", $otp, now()->addMinutes(10));

        // Set rate-limit flag (TTL: 60 seconds)
        Cache::put($rateLimitKey, true, now()->addSeconds(60));

        $sent = TextifyService::notifyUser(
            $phone,
            "Your Bustisha verification code is: {$otp}\nThis code expires in 10 minutes. Do not share it with anyone."
        );

        if (!$sent) {
            return response()->json(['message' => 'Failed to send OTP. Check your phone number or try again.'], 500);
        }

        return response()->json(['message' => 'OTP sent successfully.']);
    }

    // ─── Register ────────────────────────────────────────────────────────────

    public function register(Request $request)
    {
        $data = $request->validate([
            'name'                  => 'required|string|max:255',
            'username'              => 'required|string|max:50|unique:users',
            'email'                 => 'required|email|unique:users',
            'phone'                 => 'required|string|max:20',
            'password'              => 'required|string|min:6|confirmed',
            'otp'                   => 'required|string|size:5',
        ]);

        // Verify OTP
        $cachedOtp = Cache::get("otp:{$data['phone']}");

        if (!$cachedOtp || $cachedOtp !== $data['otp']) {
            return response()->json([
                'message' => 'Invalid or expired OTP. Please request a new code.',
                'errors'  => ['otp' => ['Invalid or expired OTP.']],
            ], 422);
        }

        // OTP valid — remove it so it can't be reused
        Cache::forget("otp:{$data['phone']}");

        $user = User::create([
            'name'        => $data['name'],
            'username'    => $data['username'],
            'email'       => $data['email'],
            'phone'       => $data['phone'],
            'password'    => Hash::make($data['password']),
            'balance'     => 0,
            'total_spent' => 0,
            'role'        => 'user',
            'status'      => 'active',
        ]);

        // Send welcome SMS
        TextifyService::notifyUser(
            $user->phone,
            "Hello {$user->username}, your Bustisha account has been created successfully. Deposit to start placing orders."
        );

        return response()->json([
            'user'  => $user,
            'token' => $user->createToken('api')->plainTextToken,
        ], 201);
    }

    // ─── Login ───────────────────────────────────────────────────────────────

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        if (!Hash::check($request->password, $user->password)) {
            $user->increment('failed_login_attempts');
            if ($user->failed_login_attempts >= 4) {
                $user->update(['status' => 'suspended']);
                return response()->json(['message' => 'Account suspended due to too many failed attempts.'], 403);
            }
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        if ($user->status !== 'active') {
            return response()->json(['message' => 'Account is suspended.'], 403);
        }

        $user->update([
            'failed_login_attempts' => 0,
            'last_active_at'        => now(),
        ]);

        return response()->json([
            'user'  => $user,
            'token' => $user->createToken('api')->plainTextToken,
        ]);
    }

    // ─── Logout ──────────────────────────────────────────────────────────────

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out.']);
    }
}

