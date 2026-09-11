<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SettingController extends Controller
{
    public function index()
    {
        $settings = Setting::whereNotIn('key', ['mobilipa_api_key', 'sonicpesa_api_key', 'textify_api_key'])->pluck('value', 'key');
        $settings['mobilipa_api_key_set']  = filled(Setting::getSecret('mobilipa_api_key'));
        $settings['sonicpesa_api_key_set'] = filled(Setting::getSecret('sonicpesa_api_key'));
        $settings['textify_api_key_set']   = filled(Setting::getSecret('textify_api_key'));
        return $settings;
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'min_deposit'            => 'sometimes|numeric|min:100',
            'whatsapp_number'        => 'nullable|string|max:30',
            'conversion_rate'        => 'sometimes|numeric|min:1',
            'categories'             => 'nullable|string',
            'platforms'              => 'nullable|string',
            'active_payment_gateway' => ['nullable', Rule::in(['mobilipa', 'sonicpesa'])],
            'mobilipa_api_key'       => 'nullable|string|max:1000',
            'sonicpesa_api_key'      => 'nullable|string|max:1000',
            'textify_api_key'        => 'nullable|string|max:1000',
            'admin_phone'            => 'nullable|string|max:30',
        ]);

        $keys = ['min_deposit', 'whatsapp_number', 'conversion_rate', 'categories', 'platforms', 'active_payment_gateway', 'admin_phone'];
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                Setting::updateOrCreate(['key' => $key], ['value' => trim((string) ($data[$key] ?? ''))]);
            }
        }

        foreach (['mobilipa_api_key', 'sonicpesa_api_key', 'textify_api_key'] as $key) {
            if (array_key_exists($key, $data) && filled($data[$key])) {
                $cleaned = trim((string) $data[$key]);
                if ($key === 'textify_api_key') {
                    $cleaned = preg_replace('/^Bearer\s+/i', '', $cleaned);
                }
                Setting::setSecret($key, $cleaned);
            }
        }

        return response()->json([
            'message'              => 'Settings updated.',
            'textify_api_key_set'  => filled(Setting::getSecret('textify_api_key')),
            'admin_phone'          => Setting::getValue('admin_phone', ''),
        ]);
    }

    public function getMetadata()
    {
        $rawCategories = Setting::getValue('categories', '');

        $platforms = Service::where('is_active', true)
            ->whereNotNull('platform')
            ->where('platform', '!=', '')
            ->distinct()
            ->pluck('platform')
            ->map(fn($p) => trim($p))
            ->filter()
            ->values()
            ->sort()
            ->values()
            ->all();

        if (empty($platforms)) {
            $rawPlatforms = Setting::getValue('platforms', '');
            $platforms = $rawPlatforms ? array_map('trim', explode(',', $rawPlatforms)) : [];
        }

        return response()->json([
            'categories' => $rawCategories ? array_map('trim', explode(',', $rawCategories)) : ['Followers', 'Likes', 'Comments', 'Views', 'Subscribers', 'Members', 'Plays', 'Shares'],
            'platforms' => $platforms,
        ]);
    }

    public function paymentMethods()
    {
        $active = Setting::getValue('active_payment_gateway');
        if (!in_array($active, ['mobilipa', 'sonicpesa'], true) || !filled(Setting::getSecret($active.'_api_key'))) {
            return response()->json([]);
        }

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
