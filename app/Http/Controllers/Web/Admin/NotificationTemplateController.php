<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationTemplate;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class NotificationTemplateController extends Controller
{
    private const EVENTS = ['account', 'service_request', 'quotation', 'order', 'payment', 'withdrawal', 'message', 'review'];

    private const VARIABLES = ['user_name', 'reference', 'service_title', 'amount', 'currency', 'action_url'];

    public function index(): View
    {
        return view('admin.communication.templates.index', [
            'templates' => NotificationTemplate::query()->latest()->paginate(20),
            'events' => self::EVENTS,
        ]);
    }

    public function store(Request $request, AuditLogService $audit): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'alpha_dash', 'max:120'],
            'event_key' => ['required', 'in:'.implode(',', self::EVENTS)],
            'category' => ['required', 'in:account,requests,quotes,orders,payments,messages,reviews'],
            'channel' => ['required', 'in:database,mail'],
            'locale' => ['required', 'in:fr,en'],
            'subject' => ['nullable', 'string', 'max:180'],
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:10000'],
        ]);

        $this->validateVariables($data['title'].' '.$data['body']);

        $template = DB::transaction(function () use ($data, $request): NotificationTemplate {
            $template = NotificationTemplate::query()->create([
                ...$data,
                'status' => 'draft',
                'version' => 1,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
            $template->versions()->create([
                'version' => 1,
                'content' => collect($data)->only(['subject', 'title', 'body'])->all(),
                'created_by' => $request->user()->id,
            ]);

            return $template;
        });

        $audit->record('notification_template_created', $template, $request->user(), ['code' => $template->code], $request);

        return back()->with('success', 'Le modèle a été enregistré en brouillon.');
    }

    public function publish(Request $request, NotificationTemplate $template, AuditLogService $audit): RedirectResponse
    {
        abort_unless($template->status === 'draft', 409, 'Seuls les brouillons peuvent être publiés.');

        DB::transaction(function () use ($template, $request): void {
            $locked = NotificationTemplate::query()->lockForUpdate()->findOrFail($template->id);
            abort_unless($locked->status === 'draft', 409);
            $locked->update(['status' => 'published', 'published_at' => now(), 'updated_by' => $request->user()->id]);
        });

        $audit->record('notification_template_published', $template, $request->user(), ['version' => $template->version], $request);

        return back()->with('success', 'La version du modèle est publiée. Les notifications déjà créées ne sont pas modifiées.');
    }

    private function validateVariables(string $content): void
    {
        preg_match_all('/\{\{\s*([a-z_]+)\s*\}\}/', $content, $matches);
        foreach (array_unique($matches[1] ?? []) as $variable) {
            if (! in_array($variable, self::VARIABLES, true)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'body' => 'Variable non autorisée : '.$variable,
                ]);
            }
        }

        if (str_contains($content, '<script') || str_contains($content, '<?')) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'body' => 'Le contenu doit rester du texte et ne peut contenir de code exécutable.',
            ]);
        }
    }
}
