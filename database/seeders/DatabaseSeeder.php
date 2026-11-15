<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SettingSeeder::class,
            UserGroupSeeder::class,
            AdminUserSeeder::class,
            PaymentMethodSeeder::class,
            CatalogSeeder::class,
        ]);
    }
}
