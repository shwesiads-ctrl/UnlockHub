<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\KnowledgeBaseFaq;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KnowledgeBaseFaqController extends Controller
{
    public function index(Request $request): View
    {
        $faqs = KnowledgeBaseFaq::query()
            ->when($request->filled('q'), fn ($query) => $query->where('question', 'like', '%'.trim($request->string('q')).'%'))
            ->orderBy('sort')->orderBy('id')->paginate(20)->withQueryString();

        return view('admin.content.faqs.index', compact('faqs'));
    }

    public function create(): View
    {
        return view('admin.content.faqs.form', ['faq' => new KnowledgeBaseFaq]);
    }

    public function store(Request $request): RedirectResponse
    {
        $faq = KnowledgeBaseFaq::create($this->validated($request));
        ActivityLog::record('knowledge_base.faq_created', $faq);

        return to_route('admin.kb-faqs.index')->with('success', 'Question and answer added.');
    }

    public function edit(KnowledgeBaseFaq $kbFaq): View
    {
        return view('admin.content.faqs.form', ['faq' => $kbFaq]);
    }

    public function update(Request $request, KnowledgeBaseFaq $kbFaq): RedirectResponse
    {
        $kbFaq->update($this->validated($request));
        ActivityLog::record('knowledge_base.faq_updated', $kbFaq);

        return to_route('admin.kb-faqs.index')->with('success', 'Question and answer saved.');
    }

    public function destroy(KnowledgeBaseFaq $kbFaq): RedirectResponse
    {
        ActivityLog::record('knowledge_base.faq_deleted', null, ['question' => $kbFaq->question]);
        $kbFaq->delete();

        return to_route('admin.kb-faqs.index')->with('success', 'Question and answer deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:255'],
            'answer' => ['required', 'string', 'max:20000'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'is_published' => ['sometimes', 'boolean'],
        ]);

        return [
            'question' => $data['question'],
            'answer' => $data['answer'],
            'sort' => (int) ($data['sort'] ?? 0),
            'is_published' => $request->boolean('is_published'),
        ];
    }
}
