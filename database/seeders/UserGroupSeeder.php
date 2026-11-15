<?php

namespace Database\Seeders;

use App\Models\UserGroup;
use Illuminate\Database\Seeder;

class UserGroupSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            ['name' => 'Customer', 'slug' => 'customer', 'is_default' => true, 'sort' => 1],
            ['name' => 'Reseller', 'slug' => 'reseller', 'is_default' => false, 'sort' => 2],
            ['name' => 'VIP', 'slug' => 'vip', 'is_default' => false, 'sort' => 3],
        ];

        foreach ($groups as $g) {
            UserGroup::updateOrCreate(['slug' => $g['slug']], $g);
        }
    }
}
