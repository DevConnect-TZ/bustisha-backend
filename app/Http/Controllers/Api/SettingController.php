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
            'min_deposit' => 'required|numeric|min:100',
        ]);

        Setting::updateOrCreate(['key' => 'min_deposit'], ['value' => $data['min_deposit']]);

        return response()->json(['message' => 'Settings updated.', 'min_deposit' => $data['min_deposit']]);
    }

    public function minDeposit()
    {
        return response()->json(['min_deposit' => Setting::getValue('min_deposit', 1000)]);
    }
}
