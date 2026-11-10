<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\HomepageSlide;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HomepageSlideController extends Controller
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function index(): View
    {
        return view('admin.content.homepage-slides', [
            'slides' => HomepageSlide::query()->orderBy('sort_order')->orderByDesc('id')->paginate(20),
        ]);
    }

    private function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:180'],
            'subtitle' => ['required', 'string', 'max:2000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'mobile_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'image_alt' => ['nullable', 'string', 'max:255'],
            'primary_label' => ['required', 'string', 'max:80'],
            'primary_route' => ['required', Rule::in(['/search', '/professionals', '/register'])],
            'secondary_label' => ['nullable', 'string', 'max:80'],
            'secondary_route' => ['nullable', Rule::in(['/search', '/professionals', '/register'])],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules());
        $slide = HomepageSlide::create([
            'title' => $data['title'],
            'subtitle' => $data['subtitle'],
            'image_path' => $request->file('image')?->store('homepage-slides', 'public'),
            'mobile_image_path' => $request->file('mobile_image')?->store('homepage-slides', 'public'),
            'image_alt' => $data['image_alt'] ?? null,
            'primary_label' => $data['primary_label'],
            'primary_route' => $data['primary_route'],
            'secondary_label' => $data['secondary_label'] ?? null,
            'secondary_route' => $data['secondary_route'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'status' => HomepageSlide::STATUS_DRAFT,
        ]);
        $this->audit->record('homepage_slide_created', $slide, $request->user(), [], $request);

        return back()->with('success', 'Diapositive créée en brouillon.');
    }

    public function update(Request $request, HomepageSlide $slide): RedirectResponse
    {
        $data = $request->validate($this->rules());
        $updates = [
            'title' => $data['title'],
            'subtitle' => $data['subtitle'],
            'image_alt' => $data['image_alt'] ?? null,
            'primary_label' => $data['primary_label'],
            'primary_route' => $data['primary_route'],
            'secondary_label' => $data['secondary_label'] ?? null,
            'secondary_route' => $data['secondary_route'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
        ];
        foreach (['image' => 'image_path', 'mobile_image' => 'mobile_image_path'] as $input => $column) {
            if ($request->hasFile($input)) {
                $newPath = $request->file($input)->store('homepage-slides', 'public');
                if ($slide->{$column}) {
                    Storage::disk('public')->delete($slide->{$column});
                }
                $updates[$column] = $newPath;
            }
        }
        $slide->update($updates);
        $this->audit->record('homepage_slide_updated', $slide, $request->user(), [], $request);

        return back()->with('success', 'Diapositive mise à jour.');
    }

    public function publish(Request $request, HomepageSlide $slide): RedirectResponse
    {
        $slide->update(['status' => HomepageSlide::STATUS_PUBLISHED]);
        $this->audit->record('homepage_slide_published', $slide, $request->user(), [], $request);

        return back()->with('success', 'Diapositive publiée selon sa période de visibilité.');
    }

    public function archive(Request $request, HomepageSlide $slide): RedirectResponse
    {
        $slide->update(['status' => HomepageSlide::STATUS_ARCHIVED]);
        foreach ([$slide->image_path, $slide->mobile_image_path] as $path) {
            if (is_string($path) && $path !== '') {
                Storage::disk('public')->delete($path);
            }
        }
        $this->audit->record('homepage_slide_archived', $slide, $request->user(), [], $request);

        return back()->with('success', 'Diapositive archivée.');
    }
}
