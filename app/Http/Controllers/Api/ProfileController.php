<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TextifyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'user' => $user,
            'stats' => [
                'total_orders' => $user->orders()->count(),
                'completed_orders' => $user->orders()->where('status', 'completed')->count(),
                'total_spent' => $user->total_spent,
                'balance' => $user->balance,
            ],
        ]);
    }

    public function badges(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'unread_tickets' => $user->tickets()->where('status', 'replied')->count(),
            'failed_orders' => $user->orders()->where('status', 'cancelled')->count(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'username' => 'sometimes|string|max:50|unique:users,username,' . $request->user()->id,
            'phone' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:6|confirmed',
        ]);

        $user = $request->user();

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        return response()->json($user);
    }

    // ─── Existing User Phone Verification ────────────────────────────────────

    /**
     * Send 5-digit OTP for phone verification to existing user account.
     */
    public function sendPhoneOtp(Request $request)
    {
        $data = $request->validate([
            'phone' => 'required|string|max:20',
        ]);

        $phone = ltrim(trim($data['phone']), '+');

        // Check if phone number is already registered to another user
        $exists = User::where('phone', $phone)
            ->where('id', '!=', $request->user()->id)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'This phone number is already linked to another account.'], 422);
        }

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

        // 2. 60-second cooldown
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

        // Update count & set cooldown
        $newCount = $count + 1;
        if ($count === 0) {
            Cache::put($countKey, $newCount, now()->addHours(3));
        } else {
            Cache::put($countKey, $newCount, now()->addHours(3));
            Cache::put($lockKey, now()->addHours(3)->timestamp, now()->addHours(3));
        }

        Cache::put($cooldownKey, true, now()->addSeconds(60));
        $resendsLeft = max(0, 2 - $newCount);

        return response()->json([
            'message' => 'OTP sent successfully.',
            'resends_left' => $resendsLeft,
        ]);
    }

    /**
     * Confirm OTP and save phone to user profile.
     */
    public function verifyPhone(Request $request)
    {
        $data = $request->validate([
            'phone' => 'required|string|max:20',
            'otp'   => 'required|string|size:5',
        ]);

        $phone = ltrim(trim($data['phone']), '+');

        // Check if phone number is already registered to another user
        $exists = User::where('phone', $phone)
            ->where('id', '!=', $request->user()->id)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'This phone number is already linked to another account.'], 422);
        }

        $cachedOtp = Cache::get("otp:{$phone}");

        if (!$cachedOtp || $cachedOtp !== $data['otp']) {
            return response()->json([
                'message' => 'Invalid or expired verification code. Please request a new one.',
                'errors'  => ['otp' => ['Invalid or expired verification code.']],
            ], 422);
        }

        // Clear OTP & rate limit keys
        Cache::forget("otp:{$phone}");
        Cache::forget("otp_count:{$phone}");
        Cache::forget("otp_lock:{$phone}");
        Cache::forget("otp_cooldown:{$phone}");

        $user = $request->user();
        $user->phone = $phone;
        $user->save();

        // Send confirmation SMS
        TextifyService::notifyUser(
            $phone,
            "Hello {$user->username}, your phone number has been successfully verified on Bustisha! You will now receive instant order delivery alerts & exclusive offers."
        );

        return response()->json([
            'message' => 'Phone number verified successfully.',
            'user'    => $user,
        ]);
    }
}

