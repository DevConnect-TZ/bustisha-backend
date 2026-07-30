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
            'conversion_rate' => 'sometimes|numeric|min:1',
            'categories' => 'nullable|string',
            'platforms' => 'nullable|string',
            'mobilipa_enabled' => 'sometimes|boolean',
            'mobilipa_api_key' => 'nullable|string|max:255',
            'mobilipa_api_secret' => 'nullable|string|max:255',
            'sonicpesa_enabled' => 'sometimes|boolean',
            'sonicpesa_api_key' => 'nullable|string|max:255',
            'sonicpesa_api_secret' => 'nullable|string|max:255',
        ]);

        $keys = ['min_deposit', 'whatsapp_number', 'conversion_rate', 'categories', 'platforms', 'mobilipa_enabled', 'mobilipa_api_key', 'mobilipa_api_secret', 'sonicpesa_enabled', 'sonicpesa_api_key', 'sonicpesa_api_secret'];
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                Setting::updateOrCreate(['key' => $key], ['value' => $data[$key] ?? '']);
            }
        }

        return response()->json(['message' => 'Settings updated.']);
    }

    public function getMetadata()
    {
        $rawCategories = Setting::getValue('categories', '');
        $rawPlatforms = Setting::getValue('platforms', '');

        return response()->json([
            'categories' => $rawCategories ? array_map('trim', explode(',', $rawCategories)) : ['Followers', 'Likes', 'Comments', 'Views', 'Subscribers', 'Members', 'Plays', 'Shares'],
            'platforms' => $rawPlatforms ? array_map('trim', explode(',', $rawPlatforms)) : [],
        ]);
    }

    public function paymentMethods()
    {
        return response()->json([
            ['id' => 'mpesa', 'name' => 'M-Pesa', 'logo' => '/payment logo/mpesa.jpeg'],
            ['id' => 'mixxbyyas', 'name' => 'Mixx by Yas', 'logo' => '/payment logo/mixxbyyas.jpeg'],
            ['id' => 'airtel_money', 'name' => 'Airtel Money', 'logo' => '/payment logo/airtel money.png'],
            ['id' => 'halotel', 'name' => 'Halotel', 'logo' => '/payment logo/halopesa.png'],
        ]);
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
