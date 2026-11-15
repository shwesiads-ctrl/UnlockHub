<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        $categoryPage = ServiceCategory::withCount('services')->orderBy('type')->orderBy('sort')->orderBy('name')->orderBy('id')->paginate(20)->withQueryString();
        $categories = $categoryPage->getCollection()->groupBy('type');

        return view('admin.categories.index', compact('categories', 'categoryPage'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(Service::TYPES))],
            'name' => ['required', 'string', 'max:100'],
            'sort' => ['nullable', 'integer', 'min:0'],
        ]);

        $category = ServiceCategory::create($data + [
            'slug' => $this->uniqueSlug($data['type'].'-'.$data['name']),
            'sort' => $data['sort'] ?? 0,
        ]);

        ActivityLog::record('category.created', $category);

        return back()->with('success', "Group \"{$category->name}\" added.");
    }

    public function update(Request $request, ServiceCategory $category): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'sort' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $category->update([
            'name' => $data['name'],
            'sort' => $data['sort'] ?? 0,
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', 'Group saved.');
    }

    public function destroy(ServiceCategory $category): RedirectResponse
    {
        if ($category->services()->exists()) {
            return back()->with('error', 'Move or delete the services in this group first.');
        }

        $category->delete();
        ActivityLog::record('category.deleted', null, ['name' => $category->name]);

        return back()->with('success', 'Group deleted.');
    }

    private function uniqueSlug(string $base): string
    {
        $slug = Str::slug($base) ?: Str::lower(Str::random(6));
        $i = 1;
        while (ServiceCategory::where('slug', $slug)->exists()) {
            $slug = Str::slug($base).'-'.++$i;
        }

        return $slug;
    }
}
