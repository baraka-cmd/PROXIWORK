<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Service\AdminServiceIndexRequest;
use App\Models\Category;
use App\Models\ProfessionalProfile;
use App\Models\Service;
use App\Services\Admin\Service\AdminServiceManagementService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function __construct(
        private readonly AdminServiceManagementService $serviceManager,
    ) {
    }

    public function index(AdminServiceIndexRequest $request): View
    {
        return view('admin.services.index', [
            'services' => $this->serviceManager->paginate($request->validated()),
            'filters' => $request->validated(),
            'categories' => Category::query()
                ->active()
                ->orderBy('name')
                ->get(['id', 'name']),
            'professionals' => ProfessionalProfile::query()
                ->with('user')
                ->whereHas('user', fn ($query) => $query->where('account_status', 'active'))
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    public function show(Service $service): View
    {
        $this->authorize('adminView', $service);

        return view('admin.services.show', [
            'service' => $this->serviceManager->show($service),
        ]);
    }

    public function publish(Request $request, Service $service): RedirectResponse
    {
        $this->authorize('adminManage', $service);

        $this->serviceManager->publish($service, $request->user());

        return back()->with('success', 'Le service a été publié.');
    }

    public function unpublish(Request $request, Service $service): RedirectResponse
    {
        $this->authorize('adminManage', $service);

        $this->serviceManager->unpublish($service, $request->user());

        return back()->with('success', 'Le service a été dépublié.');
    }

    public function archive(Request $request, Service $service): RedirectResponse
    {
        $this->authorize('adminManage', $service);

        $this->serviceManager->archive($service, $request->user());

        return back()->with('success', 'Le service a été archivé.');
    }
}
