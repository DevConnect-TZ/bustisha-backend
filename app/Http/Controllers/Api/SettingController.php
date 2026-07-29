<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index()
    {
        return Setting::pluck('value', 'key');
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'min_deposit' => 'sometimes|numeric|min:100',
            'whatsapp_number' => 'nullable|string|max:20',
        ]);

        if (isset($data['min_deposit'])) {
            Setting::updateOrCreate(['key' => 'min_deposit'], ['value' => $data['min_deposit']]);
        }
        if (array_key_exists('whatsapp_number', $data)) {
            Setting::updateOrCreate(['key' => 'whatsapp_number'], ['value' => $data['whatsapp_number'] ?? '']);
        }

        return response()->json(['message' => 'Settings updated.']);
    }

    public function minDeposit()
    {
        return response()->json(['min_deposit' => Setting::getValue('min_deposit', 1000)]);
    }

    public function whatsapp()
    {
        return response()->json(['number' => Setting::getValue('whatsapp_number', '')]);
    }
}
