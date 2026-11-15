<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Download;
use App\Models\Testimonial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Testimonials, Downloads and Announcements (small, single-page editors). */
class ContentExtrasController extends Controller
{
    /* ---------- Testimonials ---------- */

    public function testimonials(Request $request): View
    {
        $pending = $request->query('show') !== 'approved';

        return view('admin.content.testimonials', [
            'testimonials' => Testimonial::with('user:id,username')
                ->where('is_approved', ! $pending)->latest()->paginate(30)->withQueryString(),
            'pending' => $pending,
            'pendingCount' => Testimonial::where('is_approved', false)->count(),
        ]);
    }

    public function storeTestimonial(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'message' => ['required', 'string', 'max:600'],
            'rating' => ['required', 'integer', 'between:1,5'],
        ]);

        Testimonial::create($data + ['is_approved' => true]);

        return back()->with('success', 'Review added.');
    }

    public function updateTestimonial(Request $request, Testimonial $testimonial): RedirectResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:600'],
            'is_approved' => ['boolean'],
        ]);

        $testimonial->update(['message' => $data['message'], 'is_approved' => $request->boolean('is_approved')]);

        return back()->with('success', $testimonial->is_approved ? 'Approved — now public.' : 'Hidden.');
    }

    public function destroyTestimonial(Testimonial $testimonial): RedirectResponse
    {
        $testimonial->delete();

        return back()->with('success', 'Review deleted.');
    }

    /* ---------- Downloads ---------- */

    public function downloads(): View
    {
        return view('admin.content.downloads', ['downloads' => Download::latest()->paginate(20)->withQueryString()]);
    }

    public function storeDownload(Request $request): RedirectResponse
    {
        $download = new Download;
        $this->saveDownload($download, $request);

        return back()->with('success', 'Download added.');
    }

    public function updateDownload(Request $request, Download $download): RedirectResponse
    {
        $this->saveDownload($download, $request);

        return back()->with('success', 'Download saved.');
    }

    public function destroyDownload(Download $download): RedirectResponse
    {
        if ($download->file_path) {
            Storage::disk(Download::diskFor($download->file_path))->delete($download->file_path);
        }
        $download->delete();

        return back()->with('success', 'Download deleted.');
    }

    private function saveDownload(Download $download, Request $request): void
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'version' => ['nullable', 'string', 'max:30'],
            'url' => ['nullable', 'url:http,https', 'max:500'],
            'file' => ['nullable', 'file', 'max:102400'], // 100 MB
            'is_active' => ['boolean'],
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            // Never accept files a web server could run (shell.php, x.php.zip, .htaccess, .svg/.html pages)
            if (\App\Support\SafeName::isDangerous($file->getClientOriginalName())) {
                throw ValidationException::withMessages(['file' => 'This file type is not allowed. Zip it first (e.g. tool.zip).']);
            }
            if ($download->file_path) {
                Storage::disk(Download::diskFor($download->file_path))->delete($download->file_path);
            }
            // Private disk: files are only sent through /downloads/{id}, never opened directly from the web.
            $download->file_path = $file->storeAs(
                'downloads',
                now()->format('YmdHis').'-'.preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientOriginalName()),
                'local'
            );
        }

        if (! $download->file_path && blank($data['url'] ?? null)) {
            throw ValidationException::withMessages(['url' => 'Give a link or upload a file.']);
        }

        $download->fill([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'version' => $data['version'] ?? null,
            'url' => $data['url'] ?? null,
            'is_active' => $request->boolean('is_active', ! $download->exists),
        ])->save();
    }

    /* ---------- Announcements ---------- */

    public function announcements(): View
    {
        return view('admin.content.announcements', ['announcements' => Announcement::latest()->paginate(20)->withQueryString()]);
    }

    public function storeAnnouncement(Request $request): RedirectResponse
    {
        Announcement::create($this->announcementData($request));

        return back()->with('success', 'Announcement added.');
    }

    public function updateAnnouncement(Request $request, Announcement $announcement): RedirectResponse
    {
        $announcement->update($this->announcementData($request));

        return back()->with('success', 'Announcement saved.');
    }

    public function destroyAnnouncement(Announcement $announcement): RedirectResponse
    {
        $announcement->delete();

        return back()->with('success', 'Announcement deleted.');
    }

    private function announcementData(Request $request): array
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:255'],
            'link' => ['nullable', 'url:http,https', 'max:255'],
            'style' => ['required', Rule::in(['info', 'success', 'warning', 'danger'])],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        return $data + ['is_active' => $request->boolean('is_active')];
    }
}
