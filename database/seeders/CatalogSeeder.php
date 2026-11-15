<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Example catalog so the pages have something to show.
 * Prices are 0 (hidden) on purpose — set real prices in Admin › Services.
 */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        if (Service::exists()) {
            return;
        }

        $catalog = [
            ['imei', 'Samsung', [
                ['Samsung Worldwide NCK + MCK (up to S23)', '1-7 Days', true, 'imei'],
                ['Samsung Info & Knox Check', '1-10 Minutes', false, 'imei'],
            ]],
            ['imei', 'iPhone · USA Carrier', [
                ['AT&T USA iPhone Clean Unlock', '1-7 Days', true, 'imei'],
                ['T-Mobile / Sprint iPhone (Clean Only)', '1-5 Days', false, 'imei'],
            ]],
            ['imei', 'Checks', [
                ['iPhone Full Info Check (Carrier · Warranty · Blacklist)', '1-5 Minutes', true, 'imei'],
            ]],
            ['server', 'Tool Activations', [
                ['UnlockTool 12 Months Activation', '1-5 Minutes', true, 'username'],
                ['TFM Tool Pro 1 Year', '1-30 Minutes', false, 'username'],
            ]],
            ['server', 'Credits', [
                ['Chimera Tool Credits ×100', '1-30 Minutes', true, 'username'],
                ['Dongle Renewal (Borneo / Phoenix)', '1-24 Hours', false, 'serial'],
            ]],
            ['remote', 'Rent Tools', [
                ['UnlockTool Rent · 6 Hours', 'Instant', true, null],
            ]],
            ['remote', 'Remote & Files', [
                ['Live Remote Session (Repair Help)', '1-12 Hours', false, 'model'],
                ['Firmware / Paid File Download', '1-30 Minutes', false, 'model'],
            ]],
        ];

        $fieldDefs = [
            'imei' => ['label' => 'IMEI', 'name' => 'imei', 'input_type' => 'text', 'validation' => 'imei', 'placeholder' => '15 digits', 'is_bulk_field' => true],
            'username' => ['label' => 'Username / Email', 'name' => 'username', 'input_type' => 'text', 'validation' => 'none', 'placeholder' => 'Tool account username'],
            'serial' => ['label' => 'Serial Number', 'name' => 'serial', 'input_type' => 'text', 'validation' => 'serial', 'placeholder' => 'Dongle / box serial'],
            'model' => ['label' => 'Device model', 'name' => 'model', 'input_type' => 'text', 'validation' => 'none', 'placeholder' => 'e.g. Redmi Note 13'],
        ];

        foreach ($catalog as $ci => [$type, $catName, $services]) {
            $cat = ServiceCategory::create([
                'type' => $type,
                'name' => $catName,
                'slug' => Str::slug($type.'-'.$catName),
                'sort' => $ci,
            ]);

            foreach ($services as $si => [$name, $time, $hot, $field]) {
                $service = Service::create([
                    'service_category_id' => $cat->id,
                    'type' => $type,
                    'name' => $name,
                    'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
                    'delivery_time' => $time,
                    'is_hot' => $hot,
                    'allow_bulk' => $field === 'imei',
                    'processing_mode' => 'manual',
                    'status' => 'hidden', // set a price, then make it Active
                    'sort' => $si,
                ]);

                if ($field) {
                    $service->fields()->create($fieldDefs[$field] + ['is_required' => true, 'sort' => 0]);
                }
            }
        }
    }
}
