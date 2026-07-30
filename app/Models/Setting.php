<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $setting = self::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    public static function getSecret(string $key): ?string
    {
        $value = self::getValue($key);
        if (!$value) return null;

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            // Supports credentials saved before encryption was introduced.
            return $value;
        }
    }

    public static function setSecret(string $key, string $value): void
    {
        self::updateOrCreate(['key' => $key], ['value' => Crypt::encryptString($value)]);
    }
}
