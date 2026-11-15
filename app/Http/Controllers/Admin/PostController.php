<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Blog posts and Knowledge Base articles (Markdown). */
class PostController extends Controller
{
    public function index(Request $request): View
    {
        $type = in_array($request->query('type'), ['blog', 'kb'], true) ? $request->query('type') : 'kb';

        $posts = Post::where('type', $type)
            ->when($request->query('q'), fn ($q, $s) => $q->where('title', 'like', "%{$s}%"))
            ->orderByDesc('updated_at')
            ->paginate(30)->withQueryString();

        return view('admin.content.posts.index', compact('posts', 'type'));
    }

    public function create(Request $request): View
    {
        return view('admin.content.posts.form', ['post' => new Post([
            'type' => $request->query('type') === 'blog' ? 'blog' : 'kb',
            'is_published' => true,
        ])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $post = new Post(['author_id' => $request->user()->id]);
        $this->save($post, $request);

        return redirect()->route('admin.posts.edit', $post)->with('success', 'Saved.');
    }

    public function edit(Post $post): View
    {
        return view('admin.content.posts.form', compact('post'));
    }

    public function update(Request $request, Post $post): RedirectResponse
    {
        $this->save($post, $request);

        return back()->with('success', 'Saved.');
    }

    public function destroy(Post $post): RedirectResponse
    {
        if ($post->cover_path) {
            Storage::disk('public')->delete($post->cover_path);
        }
        $type = $post->type;
        $post->delete();

        return redirect()->route('admin.posts.index', ['type' => $type])->with('success', 'Deleted.');
    }

    private function save(Post $post, Request $request): void
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['blog', 'kb'])],
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['nullable', 'string', 'max:200', 'alpha_dash', Rule::unique('posts', 'slug')->ignore($post->id)],
            'excerpt' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:100000'],
            'cover' => ['nullable', 'image', 'max:3072'],
            'remove_cover' => ['boolean'],
            'is_published' => ['boolean'],
            'published_at' => ['nullable', 'date'],
        ]);

        $slug = $data['slug'] ?: Str::slug($data['title']);
        if ($slug === '') {
            $slug = Str::lower(Str::random(8));
        }
        $base = $slug;
        $i = 1;
        while (Post::where('slug', $slug)->whereKeyNot($post->id)->exists()) {
            $slug = $base.'-'.++$i;
        }

        if ($request->boolean('remove_cover') && $post->cover_path) {
            Storage::disk('public')->delete($post->cover_path);
            $post->cover_path = null;
        }
        if ($request->hasFile('cover')) {
            if ($post->cover_path) {
                Storage::disk('public')->delete($post->cover_path);
            }
            $post->cover_path = $request->file('cover')->store('posts', 'public');
        }

        $published = $request->boolean('is_published');

        $post->fill([
            'type' => $data['type'],
            'title' => $data['title'],
            'slug' => $slug,
            'excerpt' => $data['excerpt'] ?? null,
            'body' => $data['body'],
            'is_published' => $published,
            'published_at' => $published ? ($data['published_at'] ?? $post->published_at ?? now()) : $post->published_at,
        ])->save();
    }
}
