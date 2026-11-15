<?php

namespace Database\Seeders;

use App\Models\UserGroup;
use Illuminate\Database\Seeder;

class UserGroupSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            ['name' => 'Customer', 'slug' => 'customer', 'is_default' => true, 'sort' => 1, 'badge_color' => '#64748B', 'badge_icon' => 'user'],
            ['name' => 'Reseller', 'slug' => 'reseller', 'is_default' => false, 'sort' => 2, 'badge_color' => '#2459E8', 'badge_icon' => 'shield'],
            ['name' => 'VIP', 'slug' => 'vip', 'is_default' => false, 'sort' => 3, 'badge_color' => '#D4A017', 'badge_icon' => 'crown'],
        ];

        foreach ($groups as $g) {
            UserGroup::updateOrCreate(['slug' => $g['slug']], $g);
        }
    }
}
