<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\SkillStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Skill;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SkillController extends Controller
{
    public function index(): View
    {
        return view('admin.skills.index', [
            'skills' => Skill::query()
                ->with('category')
                ->withCount(['professionalProfiles', 'services'])
                ->orderBy('category_id')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->paginate(25),
        ]);
    }

    public function create(): View
    {
        return view('admin.skills.form', ['skill' => null, 'categories' => $this->activeCategories()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $data['slug'] = $this->resolveSlug($data['slug'] ?? null, $data['name']);

        Skill::query()->create($data);

        return redirect()->route('admin.skills.index')->with('success', 'La compétence a été créée.');
    }

    public function edit(Skill $skill): View
    {
        return view('admin.skills.form', ['skill' => $skill, 'categories' => $this->activeCategories()]);
    }

    public function update(Request $request, Skill $skill): RedirectResponse
    {
        $data = $this->validatedData($request, $skill);
        $data['slug'] = $this->resolveSlug($data['slug'] ?? null, $data['name'], $skill);
        $skill->update($data);

        return redirect()->route('admin.skills.index')->with('success', 'La compétence a été mise à jour.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request, ?Skill $skill = null): array
    {
        $validated = Validator::make($request->all(), [
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('status', 'active')],
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'slug' => ['nullable', 'string', 'max:120', Rule::unique('skills', 'slug')->ignore($skill)],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::enum(SkillStatus::class)],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
        ])->validate();

        return $validated;
    }

    private function activeCategories(): Collection
    {
        return Category::query()->active()->orderBy('name')->get(['id', 'name']);
    }

    private function resolveSlug(?string $slug, string $name, ?Skill $skill = null): string
    {
        $baseSlug = Str::slug(filled($slug) ? $slug : $name);
        $resolvedSlug = $baseSlug;
        $suffix = 2;

        while (Skill::query()
            ->where('slug', $resolvedSlug)
            ->when($skill, fn ($query) => $query->whereKeyNot($skill->getKey()))
            ->exists()) {
            $resolvedSlug = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        return $resolvedSlug;
    }
}
