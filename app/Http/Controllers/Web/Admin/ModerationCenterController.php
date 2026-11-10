<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\OrderDispute;
use App\Models\Report;
use App\Models\Review;
use App\Models\ReviewResponse;
use App\Models\SupportTicket;
use App\Services\Review\ReviewService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ModerationCenterController extends Controller
{
    public function __construct(private readonly ReviewService $reviews) {}

    public function reviews(Request $request): View
    {
        abort_unless($request->user()?->hasPermissionTo('reviews.moderate'), 403);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:published,hidden'],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'reported' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);
        $query = Review::query()->with([
            'client:id,name,email',
            'professional.user:id,name,email',
            'order:id,order_number,status',
            'response',
        ])->withCount(['response']);

        if (($filters['search'] ?? '') !== '') {
            $term = trim($filters['search']);
            $query->where(fn ($q) => $q->where('comment', 'like', "%{$term}%")
                ->orWhereHas('client', fn ($u) => $u->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"))
                ->orWhereHas('professional.user', fn ($u) => $u->where('name', 'like', "%{$term}%"))
                ->orWhereHas('order', fn ($o) => $o->where('order_number', 'like', "%{$term}%")));
        }
        if (($filters['status'] ?? '') !== '') $query->where('status', $filters['status']);
        if (($filters['rating'] ?? '') !== '') $query->where('rating', $filters['rating']);
        if (($filters['reported'] ?? false)) {
            $query->whereExists(function ($sub): void {
                $sub->selectRaw('1')->from('reports')
                    ->whereColumn('reports.target_id', 'reviews.id')
                    ->where('reports.target_type', (new Review())->getMorphClass());
            });
        }

        return view('admin.moderation.reviews', [
            'reviews' => $query->latest('id')->paginate((int) ($filters['per_page'] ?? 25))->withQueryString(),
            'filters' => $filters,
            'counts' => [
                'all' => Review::count(),
                'published' => Review::where('status', ReviewStatus::PUBLISHED->value)->count(),
                'hidden' => Review::where('status', ReviewStatus::HIDDEN->value)->count(),
                'reported' => Report::where('target_type', (new Review())->getMorphClass())->count(),
            ],
        ]);
    }

    public function moderateReview(Request $request, Review $review)
    {
        abort_unless($request->user()?->hasPermissionTo('reviews.moderate'), 403);
        $data = $request->validate([
            'status' => ['required', 'in:published,hidden'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);
        $this->reviews->moderate($review, $request->user(), ReviewStatus::from($data['status']), $data['reason'], $request);
        return back()->with('success', 'La modération de l’avis a été enregistrée.');
    }

    public function moderateResponse(Request $request, ReviewResponse $response)
    {
        abort_unless($request->user()?->hasPermissionTo('reviews.moderate'), 403);
        $data = $request->validate([
            'status' => ['required', 'in:published,hidden'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);
        $this->reviews->moderateResponse($response, $request->user(), ReviewStatus::from($data['status']), $data['reason'], $request);
        return back()->with('success', 'La modération de la réponse a été enregistrée.');
    }

    public function reportedReviews(Request $request): View
    {
        abort_unless($request->user()?->hasPermissionTo('reports.manage'), 403);
        $type = (new Review())->getMorphClass();
        $reports = Report::query()->where('target_type', $type)
            ->with(['reporter:id,name,email', 'assignee:id,name', 'target'])
            ->withCount('moderationActions')
            ->latest('id')->paginate(25)->withQueryString();
        return view('admin.moderation.reported-reviews', compact('reports'));
    }

    public function history(Request $request): View
    {
        abort_unless($request->user()?->hasPermissionTo('moderation.history.view'), 403);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $query = AuditLog::query()->with('user:id,name,email')
            ->where(function ($q): void {
                $q->where('action', 'like', '%report.%')
                    ->orWhere('action', 'like', '%moderation.%')
                    ->orWhere('action', 'like', '%review.%')
                    ->orWhere('action', 'like', '%order_dispute.%')
                    ->orWhere('action', 'like', '%support.ticket%');
            });
        if (($filters['search'] ?? '') !== '') {
            $term = trim($filters['search']);
            $query->where(fn ($q) => $q->where('action', 'like', "%{$term}%")
                ->orWhere('subject_type', 'like', "%{$term}%")
                ->orWhere('subject_id', 'like', "%{$term}%")
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$term}%")));
        }
        if (! empty($filters['from'])) $query->whereDate('created_at', '>=', $filters['from']);
        if (! empty($filters['to'])) $query->whereDate('created_at', '<=', $filters['to']);
        return view('admin.moderation.history', [
            'logs' => $query->latest('id')->paginate(40)->withQueryString(),
            'filters' => $filters,
        ]);
    }

    public function resolutions(Request $request): View
    {
        abort_unless($request->user()?->hasPermissionTo('moderation.history.view'), 403);
        return view('admin.moderation.resolutions', [
            'reports' => [
                'pending' => Report::whereIn('status', ['pending', 'under_review'])->count(),
                'resolved' => Report::where('status', 'resolved')->count(),
                'dismissed' => Report::where('status', 'dismissed')->count(),
            ],
            'tickets' => [
                'open' => SupportTicket::whereIn('status', ['open', 'in_progress', 'waiting'])->count(),
                'resolved' => SupportTicket::where('status', 'resolved')->count(),
                'closed' => SupportTicket::where('status', 'closed')->count(),
            ],
            'disputes' => [
                'active' => OrderDispute::whereNotIn('status', ['resolved', 'closed'])->count(),
                'resolved' => OrderDispute::where('status', 'resolved')->count(),
                'closed' => OrderDispute::where('status', 'closed')->count(),
            ],
        ]);
    }
}
