<?php

namespace App\Http\Controllers;

use App\Models\Download;
use App\Models\Order;
use App\Models\Post;
use App\Models\Service;
use App\Models\Testimonial;
use App\Support\Notify;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Public pages: Knowledge Base, Blog, Testimonials, Downloads, Track Order, Contact, RSS. */
class ContentController extends Controller
{
    /* ---------- Knowledge base & blog ---------- */

    public function posts(Request $request, string $type): View
    {
        $search = trim((string) $request->query('q', ''));

        $posts = Post::published()->where('type', $type)
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('title', 'like', "%{$search}%")->orWhere('body', 'like', "%{$search}%")))
            ->orderByDesc('published_at')
            ->paginate($type === 'kb' ? 30 : 9)->withQueryString();

        return view('content.posts', compact('posts', 'type', 'search'));
    }

    public function post(string $type, string $slug): View
    {
        $post = Post::published()->where('type', $type)->where('slug', $slug)->with('author:id,name')->firstOrFail();

        $related = Post::published()->where('type', $type)->whereKeyNot($post->id)
            ->orderByDesc('published_at')->limit(4)->get(['id', 'title', 'slug', 'type', 'published_at']);

        return view('content.post', compact('post', 'related'));
    }

    /* ---------- Testimonials ---------- */

    public function testimonials(Request $request): View
    {
        return view('content.testimonials', [
            'testimonials' => Testimonial::where('is_approved', true)->latest()->paginate(18),
            'mine' => $request->user() ? Testimonial::where('user_id', $request->user()->id)->latest()->first() : null,
        ]);
    }

    public function storeTestimonial(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'min:10', 'max:600'],
            'rating' => ['required', 'integer', 'between:1,5'],
        ]);

        $user = $request->user();

        // One open (unapproved) review per user
        Testimonial::updateOrCreate(
            ['user_id' => $user->id, 'is_approved' => false],
            ['name' => $user->name, 'message' => $data['message'], 'rating' => $data['rating']]
        );

        Notify::admin('testimonial', '⭐ <b>New review ('.$data['rating'].'/5)</b> from '.Notify::e($user->username)
            ."\n".Notify::e(\Illuminate\Support\Str::limit($data['message'], 300))
            ."\n".route('admin.testimonials.index'));

        return back()->with('success', 'Thank you! Your review will appear after approval.');
    }

    /* ---------- Downloads ---------- */

    public function downloads(): View
    {
        return view('content.downloads', ['downloads' => Download::where('is_active', true)->latest()->get()]);
    }

    public function download(Download $download): RedirectResponse|StreamedResponse
    {
        abort_unless($download->is_active, 404);
        $download->increment('download_count');

        if ($download->file_path && Storage::disk('public')->exists($download->file_path)) {
            return Storage::disk('public')->download($download->file_path);
        }

        abort_unless($download->url, 404);

        return redirect()->away($download->url);
    }

    /* ---------- Track order ---------- */

    public function track(Request $request): View
    {
        $order = null;
        $query = strtoupper(trim((string) $request->query('order', '')));

        if ($query !== '') {
            $request->validate(['order' => ['string', 'max:20', 'regex:/^[A-Za-z0-9]+$/']]);
            $order = Order::where('order_no', $query)->first(['id', 'order_no', 'user_id', 'service_name', 'imei', 'status', 'created_at', 'completed_at']);
        }

        return view('content.track', [
            'query' => $query,
            'order' => $order,
            'isOwner' => $order && $request->user()?->id === $order->user_id,
        ]);
    }

    /* ---------- Contact ---------- */

    public function contact(): View
    {
        return view('content.contact');
    }

    public function sendContact(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
            'website' => ['nullable', 'size:0'], // honeypot: bots fill hidden fields
        ]);

        $to = setting('contact.email') ?: env('ADMIN_EMAIL');

        if ($to) {
            $body = "From: {$data['name']} <{$data['email']}>\n"
                .($request->user() ? "Username: {$request->user()->username}\n" : '')
                ."IP: {$request->ip()}\n\n{$data['message']}";

            Mail::raw($body, function ($m) use ($to, $data) {
                $m->to($to)->replyTo($data['email'], $data['name'])
                    ->subject('[Contact] '.$data['subject']);
            });
        }

        Notify::admin('contact', '✉️ <b>Contact: '.Notify::e($data['subject']).'</b>'
            ."\nFrom: ".Notify::e($data['name']).' &lt;'.Notify::e($data['email']).'&gt;'
            ."\n".Notify::e(\Illuminate\Support\Str::limit($data['message'], 600)));

        return back()->with('success', 'Message sent. We usually reply within a few hours ('.setting('site.support_hours', '9 AM – 10 PM').').');
    }

    /* ---------- RSS ---------- */

    public function rss(): Response
    {
        $posts = Post::published()->where('type', 'blog')->orderByDesc('published_at')->limit(20)->get();
        $services = Service::visible()->latest()->limit(20)->get(['id', 'name', 'slug', 'description', 'created_at']);

        return response()
            ->view('content.rss', compact('posts', 'services'))
            ->header('Content-Type', 'application/rss+xml; charset=UTF-8');
    }
}
