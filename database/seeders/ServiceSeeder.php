<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            ['name' => 'Instagram Followers [Real]', 'platform' => 'Instagram', 'category' => 'Followers', 'rate' => 2500, 'min_quantity' => 100, 'max_quantity' => 10000, 'description' => 'High quality real followers'],
            ['name' => 'Instagram Followers [Bot]', 'platform' => 'Instagram', 'category' => 'Followers', 'rate' => 800, 'min_quantity' => 100, 'max_quantity' => 50000, 'description' => 'Fast delivery bot followers'],
            ['name' => 'Instagram Likes [HQ]', 'platform' => 'Instagram', 'category' => 'Likes', 'rate' => 1200, 'min_quantity' => 50, 'max_quantity' => 50000, 'description' => 'High quality likes'],
            ['name' => 'Instagram Comments [Custom]', 'platform' => 'Instagram', 'category' => 'Comments', 'rate' => 5000, 'min_quantity' => 10, 'max_quantity' => 5000, 'description' => 'Custom comments'],
            ['name' => 'Instagram Views [Video]', 'platform' => 'Instagram', 'category' => 'Views', 'rate' => 500, 'min_quantity' => 100, 'max_quantity' => 100000, 'description' => 'Video views'],
            ['name' => 'YouTube Views [Retention]', 'platform' => 'YouTube', 'category' => 'Views', 'rate' => 3000, 'min_quantity' => 100, 'max_quantity' => 100000, 'description' => 'High retention views'],
            ['name' => 'YouTube Subscribers', 'platform' => 'YouTube', 'category' => 'Subscribers', 'rate' => 8000, 'min_quantity' => 50, 'max_quantity' => 20000, 'description' => 'Real subscribers'],
            ['name' => 'YouTube Likes', 'platform' => 'YouTube', 'category' => 'Likes', 'rate' => 2000, 'min_quantity' => 50, 'max_quantity' => 50000, 'description' => 'Fast likes'],
            ['name' => 'TikTok Followers [Fast]', 'platform' => 'TikTok', 'category' => 'Followers', 'rate' => 1800, 'min_quantity' => 100, 'max_quantity' => 20000, 'description' => 'Quick delivery'],
            ['name' => 'TikTok Likes', 'platform' => 'TikTok', 'category' => 'Likes', 'rate' => 900, 'min_quantity' => 50, 'max_quantity' => 100000, 'description' => 'Instant likes'],
            ['name' => 'TikTok Views', 'platform' => 'TikTok', 'category' => 'Views', 'rate' => 300, 'min_quantity' => 100, 'max_quantity' => 500000, 'description' => 'Massive views'],
            ['name' => 'Twitter Followers [Real]', 'platform' => 'Twitter', 'category' => 'Followers', 'rate' => 2000, 'min_quantity' => 50, 'max_quantity' => 50000, 'description' => 'Real followers'],
            ['name' => 'Twitter Likes', 'platform' => 'Twitter', 'category' => 'Likes', 'rate' => 600, 'min_quantity' => 50, 'max_quantity' => 100000, 'description' => 'Fast likes'],
            ['name' => 'Telegram Members', 'platform' => 'Telegram', 'category' => 'Members', 'rate' => 4000, 'min_quantity' => 100, 'max_quantity' => 50000, 'description' => 'Channel members'],
            ['name' => 'Discord Members [Online]', 'platform' => 'Discord', 'category' => 'Members', 'rate' => 6000, 'min_quantity' => 50, 'max_quantity' => 10000, 'description' => 'Online members'],
            ['name' => 'Spotify Plays', 'platform' => 'Spotify', 'category' => 'Plays', 'rate' => 1500, 'min_quantity' => 100, 'max_quantity' => 100000, 'description' => 'Track plays'],
            ['name' => 'Facebook Page Likes', 'platform' => 'Facebook', 'category' => 'Likes', 'rate' => 3000, 'min_quantity' => 100, 'max_quantity' => 20000, 'description' => 'Page likes'],
            ['name' => 'Twitch Followers', 'platform' => 'Twitch', 'category' => 'Followers', 'rate' => 5000, 'min_quantity' => 50, 'max_quantity' => 10000, 'description' => 'Channel followers'],
        ];

        foreach ($services as $s) {
            Service::create($s);
        }
    }
}
