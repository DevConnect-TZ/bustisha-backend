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
     * Enforces: 60s cooldown between requests, and maximum 2 requests (1 initial + 1 resend) per 3-hour window.
     */
    public function sendOtp(Request $request)
    {
        $data = $request->validate([
            'phone' => 'required|string|max:20',
        ]);

        $phone = $data['phone'];

        // 1. Check 3-hour lockout
        $lockKey = "otp_lock:{$phone}";
        if (Cache::has($lockKey)) {
            $lockUntil = Cache::get($lockKey);
            $minutesLeft = max(1, (int) ceil(($lockUntil - now()->timestamp) / 60));
            $hoursLeft = round($minutesLeft / 60, 1);
            return response()->json([
                'message' => "Maximum OTP requests reached. Please wait {$minutesLeft} minutes (approx. {$hoursLeft} hrs) before requesting a new code.",
                'locked' => true,
                'minutes_left' => $minutesLeft,
                'resends_left' => 0,
            ], 429);
        }

        $countKey = "otp_count:{$phone}";
        $count = (int) Cache::get($countKey, 0);

        if ($count >= 2) {
            $lockUntil = now()->addHours(3)->timestamp;
            Cache::put($lockKey, $lockUntil, now()->addHours(3));
            return response()->json([
                'message' => 'Maximum OTP requests reached. Please wait 3 hours before requesting a new code.',
                'locked' => true,
                'minutes_left' => 180,
                'resends_left' => 0,
            ], 429);
        }

        // 2. Rate-limit cooldown: one OTP request per phone per 60 seconds
        $cooldownKey = "otp_cooldown:{$phone}";
        if (Cache::has($cooldownKey)) {
            return response()->json([
                'message' => 'Please wait 60 seconds before requesting another OTP.',
                'resends_left' => max(0, 2 - $count),
            ], 429);
        }

        $otp = str_pad(random_int(10000, 99999), 5, '0', STR_PAD_LEFT);

        // Store OTP (TTL: 10 minutes)
        Cache::put("otp:{$phone}", $otp, now()->addMinutes(10));

        $sent = TextifyService::notifyUser(
            $phone,
            "Your Bustisha verification code is: {$otp}\nThis code expires in 10 minutes. Do not share it with anyone."
        );

        if (!$sent) {
            return response()->json(['message' => 'Failed to send OTP. Check your phone number or try again.'], 500);
        }

        // Update attempt count & set 60s cooldown
        $newCount = $count + 1;
        if ($count === 0) {
            Cache::put($countKey, $newCount, now()->addHours(3));
        } else {
            Cache::put($countKey, $newCount, now()->addHours(3));
            // 2nd attempt used: lock out further requests for 3 hours
            Cache::put($lockKey, now()->addHours(3)->timestamp, now()->addHours(3));
        }

        Cache::put($cooldownKey, true, now()->addSeconds(60));

        $resendsLeft = max(0, 2 - $newCount);

        return response()->json([
            'message' => 'OTP sent successfully.',
            'resends_left' => $resendsLeft,
        ]);
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

        // OTP valid — remove related cache keys
        Cache::forget("otp:{$data['phone']}");
        Cache::forget("otp_count:{$data['phone']}");
        Cache::forget("otp_lock:{$data['phone']}");
        Cache::forget("otp_cooldown:{$data['phone']}");

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

    // ─── Forgot Password ───────────────────────────────────────────────────

    /**
     * Send OTP for password reset.
     * Enforces: Phone must exist in users database, and 1 code request per day (24h limit).
     */
    public function forgotPasswordSendOtp(Request $request)
    {
        $data = $request->validate([
            'phone' => 'required|string|max:25',
        ]);

        $rawPhone = trim($data['phone']);
        $cleanPhone = preg_replace('/[^\d+]/', '', $rawPhone);
        $digitsOnly = ltrim($cleanPhone, '+');

        $user = User::where('phone', $rawPhone)
            ->orWhere('phone', $cleanPhone)
            ->orWhere('phone', $digitsOnly)
            ->orWhere('phone', '+' . $digitsOnly)
            ->when(str_starts_with($digitsOnly, '0'), function($q) use ($digitsOnly) {
                $q->orWhere('phone', '255' . substr($digitsOnly, 1))
                  ->orWhere('phone', '+255' . substr($digitsOnly, 1));
            })
            ->when(str_starts_with($digitsOnly, '255'), function($q) use ($digitsOnly) {
                $q->orWhere('phone', '0' . substr($digitsOnly, 3));
            })
            ->first();

        if (!$user) {
            return response()->json([
                'message' => 'No account found with this phone number. Please check the number or sign up.',
            ], 404);
        }

        $phoneKey = preg_replace('/[^\d]/', '', $user->phone ?: $digitsOnly);
        $lockKey = "forgot_pw_lock:{$phoneKey}";

        // Enforce 1 request per day (24-hour limit)
        if (Cache::has($lockKey)) {
            $lockUntil = (int) Cache::get($lockKey);
            $minutesLeft = max(1, (int) ceil(($lockUntil - now()->timestamp) / 60));
            $hoursLeft = round($minutesLeft / 60, 1);
            return response()->json([
                'message' => "You have already requested a password reset code today. Limit is 1 code per day. Please try again in {$hoursLeft} hours.",
                'locked' => true,
                'minutes_left' => $minutesLeft,
            ], 429);
        }

        $otp = str_pad(random_int(10000, 99999), 5, '0', STR_PAD_LEFT);
        $otpKey = "forgot_pw_otp:{$phoneKey}";

        // Store OTP for 15 minutes
        Cache::put($otpKey, $otp, now()->addMinutes(15));

        // Lock further requests for 24 hours (1 code per day)
        $lockUntil = now()->addDay()->timestamp;
        Cache::put($lockKey, $lockUntil, now()->addDay());

        // Target phone for SMS (clean digits without +)
        $smsTarget = ltrim(trim($user->phone ?: $digitsOnly), '+');
        if (str_starts_with($smsTarget, '0')) {
            $smsTarget = '255' . substr($smsTarget, 1);
        }

        $sent = TextifyService::notifyUser(
            $smsTarget,
            "Your Bustisha password reset code is: {$otp}\nThis code expires in 15 minutes. Do not share it with anyone."
        );

        if (!$sent) {
            // If SMS failed to send, remove lock so user is not blocked
            Cache::forget($lockKey);
            Cache::forget($otpKey);
            return response()->json([
                'message' => 'Failed to send SMS code. Please verify your phone number and try again.',
            ], 500);
        }

        return response()->json([
            'message' => 'Password reset code has been sent to your registered phone number.',
            'phone'   => $phoneKey,
        ]);
    }

    /**
     * Reset user password using the received OTP code.
     */
    public function forgotPasswordReset(Request $request)
    {
        $data = $request->validate([
            'phone'                 => 'required|string',
            'otp'                   => 'required|string|size:5',
            'password'              => 'required|string|min:6|confirmed',
        ]);

        $rawPhone = trim($data['phone']);
        $cleanPhone = preg_replace('/[^\d+]/', '', $rawPhone);
        $digitsOnly = ltrim($cleanPhone, '+');

        $user = User::where('phone', $rawPhone)
            ->orWhere('phone', $cleanPhone)
            ->orWhere('phone', $digitsOnly)
            ->orWhere('phone', '+' . $digitsOnly)
            ->when(str_starts_with($digitsOnly, '0'), function($q) use ($digitsOnly) {
                $q->orWhere('phone', '255' . substr($digitsOnly, 1))
                  ->orWhere('phone', '+255' . substr($digitsOnly, 1));
            })
            ->when(str_starts_with($digitsOnly, '255'), function($q) use ($digitsOnly) {
                $q->orWhere('phone', '0' . substr($digitsOnly, 3));
            })
            ->first();

        if (!$user) {
            return response()->json([
                'message' => 'User account not found.',
            ], 404);
        }

        $phoneKey = preg_replace('/[^\d]/', '', $user->phone ?: $digitsOnly);
        $otpKey = "forgot_pw_otp:{$phoneKey}";
        $cachedOtp = Cache::get($otpKey);

        if (!$cachedOtp || $cachedOtp !== $data['otp']) {
            return response()->json([
                'message' => 'Invalid or expired verification code. Please check the code or request a new one tomorrow.',
                'errors'  => ['otp' => ['Invalid or expired verification code.']],
            ], 422);
        }

        // Clear the OTP
        Cache::forget($otpKey);

        // Reset password & reactivate account if suspended due to failed logins
        $user->password = Hash::make($data['password']);
        $user->failed_login_attempts = 0;
        if ($user->status === 'suspended') {
            $user->status = 'active';
        }
        $user->save();

        return response()->json([
            'message' => 'Password reset successfully! You can now sign in with your new password.',
        ]);
    }

    // ─── Logout ──────────────────────────────────────────────────────────────

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out.']);
    }
}


