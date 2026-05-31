<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BlogPostStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BlogController extends Controller
{
    public function __construct(
        private BlogPostStore $store,
    ) {}

    public function index(): View
    {
        return view('admin.blog.index', [
            'posts' => $this->store->all(),
        ]);
    }

    public function create(): View
    {
        return view('admin.blog.form', [
            'post' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateForm($request);

        if ($validated['slug'] === '') {
            $validated['slug'] = $this->store->slugFromTitle($validated['title']);
        }

        $this->store->upsert($validated);

        return redirect()
            ->route('admin.blog.index')
            ->with('success', 'Đã thêm bài viết.');
    }

    public function edit(int $blog): View|RedirectResponse
    {
        $post = $this->store->find($blog);
        if (! $post) {
            return redirect()->route('admin.blog.index')->with('error', 'Không tìm thấy bài viết.');
        }

        return view('admin.blog.form', [
            'post' => $post,
        ]);
    }

    public function update(Request $request, int $blog): RedirectResponse
    {
        $post = $this->store->find($blog);
        if (! $post) {
            return redirect()->route('admin.blog.index')->with('error', 'Không tìm thấy bài viết.');
        }

        $validated = $this->validateForm($request, $blog);

        if ($validated['slug'] === '') {
            $validated['slug'] = $this->store->slugFromTitle($validated['title']);
        }

        $this->store->upsert($validated, $blog);

        return redirect()
            ->route('admin.blog.index')
            ->with('success', 'Đã cập nhật bài viết.');
    }

    public function destroy(int $blog): RedirectResponse
    {
        $this->store->delete($blog);

        return redirect()
            ->route('admin.blog.index')
            ->with('success', 'Đã xóa bài viết.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateForm(Request $request, ?int $ignoreId = null): array
    {
        $slugRule = ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'];

        if ($ignoreId) {
            $slugRule[] = 'unique:blog_posts,slug,'.$ignoreId;
        } else {
            $slugRule[] = 'unique:blog_posts,slug';
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'slug' => $slugRule,
            'excerpt' => ['required', 'string', 'max:500'],
            'content' => ['required', 'string'],
            'published_at' => ['required', 'date'],
            'read_time' => ['nullable', 'string', 'max:32'],
            'tags' => ['nullable', 'string', 'max:500'],
            'is_published' => ['sometimes', 'boolean'],
        ]);

        $validated['slug'] = Str::slug((string) ($validated['slug'] ?? ''));
        $validated['is_published'] = $request->boolean('is_published');
        $validated['read_time'] = trim((string) ($validated['read_time'] ?? '')) ?: '5 phút';

        return $validated;
    }
}
