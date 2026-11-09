<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Skill\StoreSkillRequest;
use App\Http\Requests\Skill\UpdateSkillRequest;
use App\Models\Category;
use App\Models\Skill;
use App\Services\Audit\AuditLogService;
use App\Services\SkillService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SkillController extends Controller
{
    public function __construct(
        private readonly SkillService $skillService,
        private readonly AuditLogService $auditLogService,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'in:active,inactive,archived'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
        ]);

        $skills = Skill::query()
            ->with('category')
            ->withCount(['professionalProfiles', 'services'])
            ->when($filters['search'] ?? null, fn ($query, $term) => $query->where(
                fn ($nested) => $nested->where('name', 'like', '%'.$term.'%')
                    ->orWhere('slug', 'like', '%'.$term.'%')
            ))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['category_id'] ?? null, fn ($query, $categoryId) => $query->where('category_id', $categoryId))
            ->orderBy('category_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('admin.skills.index', [
            'skills' => $skills,
            'filters' => $filters,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): View
    {
        return view('admin.skills.form', [
            'skill' => null,
            'categories' => $this->editableCategories(),
        ]);
    }

    public function store(StoreSkillRequest $request): RedirectResponse
    {
        $skill = $this->skillService->create($request->validated());
        $this->auditLogService->record('skill_created', $skill, $request->user(), [], $request);

        return redirect()->route('admin.skills.index')->with('success', 'La compétence a été créée.');
    }

    public function edit(Skill $skill): View
    {
        return view('admin.skills.form', [
            'skill' => $skill->load('category'),
            'categories' => $this->editableCategories($skill),
        ]);
    }

    public function update(UpdateSkillRequest $request, Skill $skill): RedirectResponse
    {
        $skill = $this->skillService->update($skill, $request->validated());
        $this->auditLogService->record('skill_updated', $skill, $request->user(), ['status' => $skill->status->value], $request);

        return redirect()->route('admin.skills.index')->with('success', 'La compétence a été mise à jour.');
    }

    public function archive(Request $request, Skill $skill): RedirectResponse
    {
        $this->skillService->archive($skill);
        $this->auditLogService->record('skill_archived', $skill, $request->user(), [], $request);

        return redirect()->route('admin.skills.index')->with('success', 'La compétence a été archivée sans supprimer ses associations.');
    }

    private function editableCategories()
    {
        return Category::query()
            ->where('status', 'active')
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id', 'status']);
    }
}
