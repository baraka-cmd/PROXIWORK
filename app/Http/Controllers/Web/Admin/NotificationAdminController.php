<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationDelivery;
use App\Models\NotificationTemplate;
use App\Services\Audit\AuditLogService;
use App\Services\Notification\NotificationTemplateRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class NotificationAdminController extends Controller
{
    public function __construct(
        private readonly AuditLogService $audit,
        private readonly NotificationTemplateRenderer $renderer,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:PENDING,PROCESSING,RETRYING,SENT,DELIVERED,FAILED,SKIPPED,SIMULATED,UNKNOWN'],
            'channel' => ['nullable', 'in:mail,sms,push'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $deliveries = NotificationDelivery::query()
            ->with(['user:id,name,email', 'notification'])
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['channel'] ?? null, fn ($q, $v) => $q->where('channel', $v))
            ->when($filters['search'] ?? null, fn ($q, $v) => $q->whereHas('user', fn ($u) => $u->where('name', 'like', '%'.$v.'%')->orWhere('email', 'like', '%'.$v.'%')))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.communication.notifications.index', [
            'deliveries' => $deliveries,
            'filters' => $filters,
            'summary' => [
                'pending' => NotificationDelivery::whereIn('status', ['PENDING', 'RETRYING'])->count(),
                'processing' => NotificationDelivery::where('status', 'PROCESSING')->count(),
                'sent' => NotificationDelivery::whereIn('status', ['SENT', 'DELIVERED'])->count(),
                'failed' => NotificationDelivery::whereIn('status', ['FAILED', 'UNKNOWN'])->count(),
            ],
        ]);
    }

    public function show(NotificationDelivery $delivery): View
    {
        $delivery->load(['user:id,name,email', 'notification']);

        return view('admin.communication.notifications.show', compact('delivery'));
    }

    public function retry(Request $request, NotificationDelivery $delivery): RedirectResponse
    {
        abort_unless($delivery->status === 'FAILED', 409, 'Seules les livraisons explicitement échouées peuvent être relancées.');
        $error = (string) $delivery->last_error_code;
        $nonRetryable = [
            'InvalidRecipientEmail',
            'RecipientOrNotificationUnavailable',
            'RECIPIENT_PHONE_UNAVAILABLE',
            'SMS_PROVIDER_NOT_CONFIGURED',
            'PUSH_PROVIDER_NOT_CONFIGURED',
            'PUSH_NO_REGISTERED_DEVICES',
        ];
        abort_if(
            $error === ''
                || in_array($error, $nonRetryable, true)
                || str_contains($error, 'Unknown')
                || str_contains($error, 'TransportException')
                || str_contains($error, 'ConnectionException')
                || str_contains($error, 'Timeout'),
            409,
            'Cette livraison ne peut pas être relancée sans corriger ou réconcilier sa cause d’échec.',
        );

        $updated = NotificationDelivery::query()
            ->whereKey($delivery->getKey())
            ->where('status', 'FAILED')
            ->update([
                'status' => 'RETRYING',
                'available_at' => now(),
                'queued_at' => null,
                'processing_at' => null,
                'last_error_code' => null,
                'updated_at' => now(),
            ]);

        abort_unless($updated === 1, 409);
        $this->audit->record('notification_delivery_retry_requested', $delivery, $request->user(), [
            'channel' => $delivery->channel,
            'previous_error_code' => $error,
        ], $request);

        return back()->with('success', 'La relance a été mise en attente.');
    }

    public function templates(): View
    {
        return view('admin.communication.templates.index', [
            'templates' => NotificationTemplate::query()->withCount('versions')->orderBy('key')->paginate(25),
        ]);
    }

    public function storeTemplate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'key' => ['required', 'alpha_dash', 'max:100', 'in:service_request,quotation,payment,order,message,review,account,campaign', 'unique:notification_templates,key'],
            'name' => ['required', 'string', 'max:160'],
            'channel' => ['required', 'in:database'],
            'locale' => ['required', 'in:fr,en'],
            'subject' => ['nullable', 'string', 'max:180'],
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:5000'],
            'variables' => ['nullable', 'array'],
            'variables.*' => ['string', 'in:prenom,nom,email,plateforme'],
        ]);
        $this->renderer->validate($data['title'], $data['body'], $data['variables'] ?? []);

        DB::transaction(function () use ($data, $request): void {
            $template = NotificationTemplate::create($data + [
                'status' => 'draft',
                'version' => 1,
                'updated_by' => $request->user()->getAuthIdentifier(),
            ]);
            $template->versions()->create([
                'version' => 1,
                'snapshot' => $template->only(['key', 'name', 'channel', 'locale', 'subject', 'title', 'body', 'variables', 'status']),
                'created_by' => $request->user()->getAuthIdentifier(),
                'created_at' => now(),
            ]);
            $this->audit->record('notification_template_created', $template, $request->user(), ['key' => $template->key], $request);
        });

        return redirect()->route('admin.communication.templates')->with('success', 'Le modèle a été créé en brouillon.');
    }

    public function updateTemplate(Request $request, NotificationTemplate $template): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'locale' => ['required', 'in:fr,en'],
            'subject' => ['nullable', 'string', 'max:180'],
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:5000'],
            'variables' => ['nullable', 'array'],
            'variables.*' => ['string', 'in:prenom,nom,email,plateforme'],
        ]);
        $this->renderer->validate($data['title'], $data['body'], $data['variables'] ?? []);

        DB::transaction(function () use ($data, $template, $request): void {
            $template->version++;
            $template->fill($data);
            $template->updated_by = $request->user()->getAuthIdentifier();
            $template->save();
            $template->versions()->create([
                'version' => $template->version,
                'snapshot' => $template->only(['key', 'name', 'channel', 'locale', 'subject', 'title', 'body', 'variables', 'status']),
                'created_by' => $request->user()->getAuthIdentifier(),
                'created_at' => now(),
            ]);
            $this->audit->record('notification_template_updated', $template, $request->user(), ['version' => $template->version], $request);
        });

        return back()->with('success', 'Le modèle a été versionné. Les notifications déjà créées ne sont pas modifiées.');
    }

    public function activateTemplate(Request $request, NotificationTemplate $template): RedirectResponse
    {
        $template->update(['status' => 'active', 'updated_by' => $request->user()->getAuthIdentifier()]);
        $this->audit->record('notification_template_activated', $template, $request->user(), ['version' => $template->version], $request);

        return back()->with('success', 'Le modèle est maintenant actif.');
    }
}