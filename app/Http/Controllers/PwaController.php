<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Services\WebPush;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PwaController extends Controller
{
    public function manifest(): JsonResponse
    {
        $name = (string) setting('site.name', config('app.name'));
        $custom = setting('pwa.icon_version');
        $icon = fn (string $file, string $fallback) => $custom && Storage::disk('public')->exists('pwa/'.$file)
            ? Storage::disk('public')->url('pwa/'.$file).'?v='.$custom
            : asset('icons/'.$fallback);

        return response()->json([
            'name' => $name,
            'short_name' => (string) setting('pwa.short_name', Str::limit($name, 12, '')),
            'description' => (string) setting('site.tagline', 'GSM unlock & tool credits'),
            'id' => '/',
            'start_url' => '/account?source=pwa',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'portrait',
            'background_color' => '#F4F6FA',
            'theme_color' => (string) setting('pwa.theme_color', '#2459E8'),
            'icons' => [
                ['src' => $icon('icon-192.png', 'icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => $icon('icon-512.png', 'icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => $icon('maskable-512.png', 'maskable-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            'shortcuts' => [
                ['name' => 'Place order', 'url' => '/services'],
                ['name' => 'My orders', 'url' => '/account/orders'],
                ['name' => 'Add funds', 'url' => '/account/funds'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'public, max-age=3600']);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:500', 'url']]);

        abort_unless(WebPush::enabled(), 404);
        if (! WebPush::validEndpoint($data['endpoint'])) {
            return response()->json(['message' => 'Unsupported push service.'], 422);
        }
        if (PushSubscription::where('user_id', $request->user()->id)->count() >= 10) {
            PushSubscription::where('user_id', $request->user()->id)->oldest('updated_at')->first()?->delete();
        }

        PushSubscription::updateOrCreate(
            ['endpoint' => $data['endpoint']],
            ['user_id' => $request->user()->id, 'user_agent' => Str::limit((string) $request->userAgent(), 250, '')],
        );

        return response()->json(['ok' => true]);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        PushSubscription::where('user_id', $request->user()->id)
            ->when($request->input('endpoint'), fn ($q, $e) => $q->where('endpoint', $e))
            ->delete();

        return response()->json(['ok' => true]);
    }

    /** What the service worker shows after a (data-less) push. */
    public function latest(Request $request): JsonResponse
    {
        $n = $request->user()->unreadNotifications()->latest()->first();

        return response()->json($n ? [
            'title' => Str::limit((string) ($n->data['title'] ?? 'New notification'), 80),
            'body' => Str::limit((string) ($n->data['body'] ?? ''), 160),
            'url' => route('account.notifications.open', $n->id),
            'tag' => $n->id,
        ] : ['title' => 'No new notifications', 'body' => '', 'url' => route('account.notifications')], 200, ['Cache-Control' => 'no-store']);
    }
}
