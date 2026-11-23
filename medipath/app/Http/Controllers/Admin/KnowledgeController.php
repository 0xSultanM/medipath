<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ModerationStatus;
use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\Review;
use App\Services\ActivityLogService;
use App\Services\NotificationService;
use App\Support\ActivityActions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** FAQ knowledge base + review moderation (admin/10, Sprints 3.6–3.7). */
class KnowledgeController extends Controller
{
    public function __construct(
        private readonly ActivityLogService $activity,
        private readonly NotificationService $notifications,
    ) {}

    public function index(Request $request): Response
    {
        $tab = $request->query('tab') === 'reviews' ? 'reviews' : 'faqs';
        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');

        $faqs = Faq::query()
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
                $query->where(fn ($inner) => $inner->where('question', 'like', $like)
                    ->orWhere('answer', 'like', $like)
                    ->orWhere('keywords', 'like', $like)
                    ->orWhere('category', 'like', $like));
            })
            ->orderBy('sort_order')
            ->orderBy('question')
            ->get();

        $reviews = Review::query()
            ->with(['patient:id,name', 'doctor:id,user_id', 'doctor.user:id,name', 'appointment:id,appointment_code,date'])
            ->when($q !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('comment', 'like', '%'.$q.'%')
                ->orWhereHas('patient', fn ($p) => $p->where('name', 'like', '%'.$q.'%'))
                ->orWhereHas('doctor.user', fn ($u) => $u->where('name', 'like', '%'.$q.'%'))))
            ->when(in_array($status, array_column(ModerationStatus::cases(), 'value'), true), fn ($query) => $query->where('moderation_status', $status))
            ->latest()
            ->get();

        $forced = (string) $request->query('state', '');
        $activeCount = $tab === 'faqs' ? $faqs->count() : $reviews->count();
        $state = in_array($forced, ['success', 'skeleton', 'empty', 'error'], true)
            ? $forced
            : ($activeCount === 0 ? 'empty' : 'success');

        return Inertia::render('Admin/Knowledge', [
            'tab' => $tab,
            'faqs' => $faqs->map(fn (Faq $faq): array => [
                'id' => $faq->id,
                'question' => $faq->question,
                'answer' => $faq->answer,
                'category' => $faq->category,
                'keywords' => $faq->keywords,
                'isPublished' => $faq->is_published,
                'sortOrder' => $faq->sort_order,
                'updatedAt' => $faq->updated_at?->toIso8601String(),
            ])->values(),
            'reviews' => $reviews->map(fn (Review $review): array => [
                'id' => $review->id,
                'patientName' => $review->patient?->name,
                'doctorName' => $review->doctor?->user?->name,
                'appointmentCode' => $review->appointment?->appointment_code,
                'appointmentDate' => $review->appointment?->date?->toDateString(),
                'rating' => $review->rating,
                'comment' => $review->comment,
                'status' => $review->moderation_status?->value,
                'statusLabel' => $review->moderation_status?->label(),
                'moderationReason' => $review->moderation_reason,
                'moderatedAt' => $review->moderated_at?->toIso8601String(),
                'createdAt' => $review->created_at?->toIso8601String(),
            ])->values(),
            'counts' => [
                'faqs' => Faq::query()->count(),
                'published' => Faq::query()->where('is_published', true)->count(),
                'reviews' => Review::query()->count(),
                'pendingReviews' => Review::query()->where('moderation_status', ModerationStatus::Pending->value)->count(),
            ],
            'filters' => ['q' => $q, 'status' => $status],
            'statuses' => collect(ModerationStatus::cases())->map(fn (ModerationStatus $s) => ['value' => $s->value, 'label' => $s->label()])->values(),
            'dataState' => $state,
            'lastKnownAt' => now()->toIso8601String(),
        ]);
    }

    public function storeFaq(Request $request): RedirectResponse
    {
        $validated = $this->validateFaq($request);
        $faq = Faq::query()->create($validated);

        $this->activity->record(ActivityActions::FAQ_CREATED, $faq, ['question' => $faq->question], $request->user());

        return back()->with('success', 'FAQ created. Chatbot retrieval uses the published set immediately.');
    }

    public function updateFaq(Request $request, Faq $faq): RedirectResponse
    {
        $faq->forceFill($this->validateFaq($request))->save();

        $this->activity->record(ActivityActions::FAQ_UPDATED, $faq, ['question' => $faq->question], $request->user());

        return back()->with('success', 'FAQ updated.');
    }

    public function toggleFaq(Request $request, Faq $faq): RedirectResponse
    {
        $faq->forceFill(['is_published' => ! $faq->is_published])->save();

        $this->activity->record(ActivityActions::FAQ_UPDATED, $faq, [
            'question' => $faq->question,
            'is_published' => $faq->is_published,
        ], $request->user());

        return back()->with('success', $faq->is_published ? 'FAQ published.' : 'FAQ unpublished. It is removed from chatbot answers but kept in history.');
    }

    public function destroyFaq(Request $request, Faq $faq): RedirectResponse
    {
        $this->activity->record(ActivityActions::FAQ_UPDATED, $faq, [
            'question' => $faq->question,
            'event' => 'deleted',
        ], $request->user());

        $faq->delete();

        return back()->with('success', 'FAQ deleted.');
    }

    public function moderateReview(Request $request, Review $review): RedirectResponse
    {
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'note' => [Rule::requiredIf($request->input('decision') === 'reject'), 'nullable', 'string', 'min:5', 'max:500'],
        ]);

        $decision = (string) $validated['decision'];
        $note = trim((string) ($validated['note'] ?? ''));

        DB::transaction(function () use ($review, $decision, $note, $request): void {
            $review->forceFill([
                'moderation_status' => $decision === 'approve' ? ModerationStatus::Approved : ModerationStatus::Rejected,
                'moderation_reason' => $decision === 'reject' ? $note : null,
                'moderated_by' => $request->user()->id,
                'moderated_at' => now(),
            ])->save();

            $this->activity->record(ActivityActions::REVIEW_MODERATED, $review, [
                'decision' => $decision,
                'note' => $note ?: null,
                'rating' => $review->rating,
            ], $request->user());
        });

        $review->loadMissing('patient.user');
        if ($review->patient?->user) {
            $this->notifications->send(
                $review->patient->user,
                'review.moderated',
                $decision === 'approve' ? 'Review published' : 'Review not published',
                $decision === 'approve'
                    ? 'Your review is now visible on the doctor profile.'
                    : "Your review was not published. Moderator note: {$note}",
                'review',
                ['review_id' => $review->id],
                '/patient/reviews',
            );
        }

        return back()->with('success', $decision === 'approve' ? 'Review published.' : 'Review hidden and rejected with a note.');
    }

    /** @return array<string, mixed> */
    private function validateFaq(Request $request): array
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'min:5', 'max:300'],
            'answer' => ['required', 'string', 'min:10', 'max:5000'],
            'category' => ['required', 'string', 'max:80'],
            'keywords' => ['nullable', 'string', 'max:255'],
            'is_published' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        return [
            'question' => trim((string) $validated['question']),
            'answer' => trim((string) $validated['answer']),
            'category' => trim((string) $validated['category']),
            'keywords' => isset($validated['keywords']) ? trim((string) $validated['keywords']) : null,
            'is_published' => (bool) ($validated['is_published'] ?? false),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ];
    }
}