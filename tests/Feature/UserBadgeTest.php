<?php

namespace Tests\Feature;

use App\Models\ResellerProfile;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class UserBadgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_and_reseller_badges(): void
    {
        $vip = UserGroup::create(['name' => 'VIP', 'slug' => 'vip', 'badge_color' => '#D4A017', 'badge_icon' => 'crown']);
        $user = $this->customer();
        $user->forceFill(['user_group_id' => $vip->id])->save();

        $html = Blade::render('<x-user-badges :user="$u" />', ['u' => $user->fresh()]);
        $this->assertStringContainsString('VIP', $html);
        $this->assertStringContainsString('#D4A017', $html);
        $this->assertStringNotContainsString('Reseller', $html);

        ResellerProfile::create(['user_id' => $user->id, 'status' => 'approved', 'shop_name' => 'S']);
        $this->assertStringContainsString('Reseller', Blade::render('<x-user-badges :user="$u" />', ['u' => $user->fresh()]));
    }
}
