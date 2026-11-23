/**
 * Admin · Knowledge base & moderation — prototypes/admin/10-faq-reviews.html.
 * FAQ CRUD/toggle feeds the deterministic chatbot; review moderation writes a
 * reviewer + note and notifies the patient. No rating row is destroyed.
 */
import { Head, router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { DataState } from '@/types';
import type { AdminFaqRow, AdminReviewRow } from '@/types/admin';
import { RoleLayout } from '@/Layouts/RoleLayout';
import { DataView } from '@/Components/Data/DataView';
import { DataTable, type DataColumn } from '@/Components/Data/DataTable';
import { StatusBadge } from '@/Components/Data/StatusBadge';
import { AdminModal } from '@/Components/Admin/AdminModal';

interface KnowledgeProps {
    tab: 'faqs' | 'reviews';
    faqs: AdminFaqRow[];
    reviews: AdminReviewRow[];
    counts: { faqs: number; published: number; reviews: number; pendingReviews: number };
    filters: { q: string; status: string };
    statuses: Array<{ value: string; label: string }>;
    dataState: DataState;
    lastKnownAt: string;
}

export default function AdminKnowledge({ tab, faqs, reviews, counts, filters, statuses, dataState, lastKnownAt }: KnowledgeProps) {
    const [state, setState] = useState<DataState>(dataState);
    const [search, setSearch] = useState(filters.q);
    const [editor, setEditor] = useState<AdminFaqRow | 'new' | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<AdminFaqRow | null>(null);
    const [rejectTarget, setRejectTarget] = useState<AdminReviewRow | null>(null);

    const switchTab = (next: 'faqs' | 'reviews') => {
        router.get('/admin/faq-reviews', next === 'reviews' ? { tab: 'reviews' } : {}, { preserveState: true, preserveScroll: true, replace: true });
    };

    const apply = (partial: Record<string, string> = {}) => {
        const params: Record<string, string> = { tab, q: filters.q, status: filters.status, ...partial };
        Object.keys(params).forEach((key) => { if (params[key] === '') delete params[key]; });
        router.get('/admin/faq-reviews', params, { preserveState: true, preserveScroll: true, replace: true });
    };

    const faqColumns: Array<DataColumn<AdminFaqRow>> = [
        {
            key: 'question',
            label: 'Question',
            render: (row) => (
                <div>
                    <strong>{row.question}</strong>
                    <div className="caption">{row.keywords || 'no keywords'} · sort {row.sortOrder}</div>
                </div>
            ),
        },
        { key: 'category', label: 'Category', render: (row) => row.category },
        {
            key: 'published',
            label: 'Published',
            render: (row) => <StatusBadge family="moderation" status={row.isPublished ? 'approved' : 'pending'} label={row.isPublished ? 'Published' : 'Draft'} />,
        },
    ];

    return (
        <RoleLayout
            role="admin"
            title="Knowledge base & moderation"
            subtitle={`${counts.published}/${counts.faqs} FAQs published · ${counts.pendingReviews} review(s) pending`}
            activeHref="/admin/faq-reviews"
            topbarActions={tab === 'faqs' ? <button type="button" className="btn btn-primary" onClick={() => setEditor('new')}>+ New FAQ</button> : null}
        >
            <Head title="Knowledge base & moderation — MediPath" />

            <DataView
                name="Data view · FAQ + review queues"
                state={state}
                onStateChange={setState}
                showSwitch
                onRetry={() => router.reload()}
                lastKnownAt={lastKnownAt}
                emptyTitle={tab === 'faqs' ? 'No FAQs yet' : 'Moderation queue clear'}
                emptyBody={tab === 'faqs' ? 'Create the first FAQ to give the chatbot a base.' : 'Patient reviews appear here after completed visits.'}
                emptyAction={tab === 'faqs' ? <button type="button" className="btn btn-primary" onClick={() => setEditor('new')}>Create first FAQ</button> : undefined}
                errorMessage="Queue failed to load. Decisions are written only server-side; retry is safe."
            >
                <div className="tabs" role="tablist" aria-label="Knowledge queues">
                    <button type="button" role="tab" aria-selected={tab === 'faqs'} onClick={() => switchTab('faqs')}>Chatbot FAQs <span className="cnt tabular">{counts.faqs}</span></button>
                    <button type="button" role="tab" aria-selected={tab === 'reviews'} onClick={() => switchTab('reviews')}>Review moderation <span className="cnt tabular">{counts.pendingReviews}</span></button>
                </div>

                <form className="filter-bar mt-m" aria-label="Knowledge filters" onSubmit={(event) => { event.preventDefault(); apply({ q: search }); }}>
                    <div className="field" style={{ margin: 0 }}>
                        <label className="label" htmlFor="kn-q">Search</label>
                        <input id="kn-q" className="input" type="search" value={search} onChange={(event) => setSearch(event.target.value)} placeholder={tab === 'faqs' ? 'question, answer, keyword' : 'patient, doctor, comment'} />
                    </div>
                    {tab === 'reviews' ? (
                        <div className="field" style={{ margin: 0 }}>
                            <label className="label" htmlFor="kn-status">Moderation</label>
                            <select id="kn-status" className="select" value={filters.status} onChange={(event) => apply({ status: event.target.value })}>
                                <option value="">Any</option>
                                {statuses.map((status) => <option key={status.value} value={status.value}>{status.label}</option>)}
                            </select>
                        </div>
                    ) : null}
                    <div className="f-actions f-full">
                        <span className="caption">{tab === 'faqs' ? counts.faqs : counts.reviews} record(s)</span>
                        <button type="button" className="btn btn-ghost mla" onClick={() => { setSearch(''); router.get('/admin/faq-reviews', { tab }, { preserveState: true, preserveScroll: true, replace: true }); }}>Clear filters</button>
                    </div>
                </form>

                {tab === 'faqs' ? (
                    <div className="mt-m">
                        <DataTable
                            className="tbl-cards-md"
                            caption="Chatbot FAQ entries"
                            columns={faqColumns}
                            rows={faqs}
                            getRowKey={(row) => row.id}
                            actions={(row) => (
                                <div className="mp-row" style={{ flexWrap: 'wrap' }}>
                                    <button type="button" className="btn btn-ghost btn-sm" onClick={() => setEditor(row)}>Edit</button>
                                    <button type="button" className="btn btn-ghost btn-sm" onClick={() => router.post(`/admin/faqs/${row.id}/toggle`, {}, { preserveScroll: true })}>
                                        {row.isPublished ? 'Unpublish' : 'Publish'}
                                    </button>
                                    <button type="button" className="btn btn-ghost btn-sm" style={{ color: 'var(--mp-danger-text)' }} onClick={() => setDeleteTarget(row)}>Delete…</button>
                                </div>
                            )}
                        />
                    </div>
                ) : (
                    <div className="stack mt-m">
                        {reviews.map((review) => (
                            <article className="card card-pad" key={review.id}>
                                <div className="spread" style={{ flexWrap: 'wrap' }}>
                                    <div>
                                        <strong>{review.patientName}</strong>
                                        <span className="caption"> · {review.doctorName} · {review.appointmentCode ?? 'no appointment'} · {review.appointmentDate ?? ''}</span>
                                        <div className="caption" aria-label={`${review.rating} of 5`}>{'★'.repeat(review.rating)}{'☆'.repeat(5 - review.rating)}</div>
                                    </div>
                                    <StatusBadge family="moderation" status={review.status} label={review.statusLabel} />
                                </div>
                                <p className="mt-s" style={{ fontSize: 14 }}>{review.comment || <span className="muted">No comment.</span>}</p>
                                {review.moderationReason ? <p className="caption">Moderator note: {review.moderationReason}</p> : null}
                                <div className="mp-row mt-m">
                                    <button type="button" className="btn btn-primary btn-sm" onClick={() => router.post(`/admin/reviews/${review.id}/moderate`, { decision: 'approve' }, { preserveScroll: true })}>Approve</button>
                                    <button type="button" className="btn btn-danger-outline btn-sm" onClick={() => setRejectTarget(review)}>Hide &amp; reject…</button>
                                    <span className="caption">reject writes reviewer id + note; patient notified.</span>
                                </div>
                            </article>
                        ))}
                    </div>
                )}
            </DataView>

            <FaqEditor faq={editor} onClose={() => setEditor(null)} />
            <DeleteFaqModal faq={deleteTarget} onClose={() => setDeleteTarget(null)} />
            <RejectReviewModal review={rejectTarget} onClose={() => setRejectTarget(null)} />
        </RoleLayout>
    );
}

function FaqEditor({ faq, onClose }: { faq: AdminFaqRow | 'new' | null; onClose: () => void }) {
    const form = useForm({ question: '', answer: '', category: 'Booking', keywords: '', is_published: true, sort_order: '0' });

    useEffect(() => {
        if (faq === 'new') {
            form.setData({ question: '', answer: '', category: 'Booking', keywords: '', is_published: true, sort_order: '0' });
        } else if (faq) {
            form.setData({
                question: faq.question,
                answer: faq.answer,
                category: faq.category,
                keywords: faq.keywords ?? '',
                is_published: faq.isPublished,
                sort_order: String(faq.sortOrder),
            });
        }
        form.clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [faq]);

    const submit = () => {
        const options = { preserveScroll: true, onSuccess: onClose };
        if (faq && faq !== 'new') form.put(`/admin/faqs/${faq.id}`, options);
        else form.post('/admin/faqs', options);
    };

    return (
        <AdminModal
            open={Boolean(faq)}
            title={faq === 'new' ? 'New FAQ' : 'Edit FAQ'}
            subtitle="Published entries are available to chatbot retrieval immediately."
            icon="💬"
            wide
            onClose={onClose}
            footer={<><button type="button" className="btn btn-quiet" onClick={onClose}>Cancel</button><button type="submit" form="faq-form" className="btn btn-primary" disabled={form.processing}>{form.processing ? 'Saving…' : 'Save & reindex'}</button></>}
        >
            <form id="faq-form" className="stack-sm" onSubmit={(event) => { event.preventDefault(); submit(); }}>
                <div className="field">
                    <label className="label req" htmlFor="faq-question">Question</label>
                    <input id="faq-question" className="input" value={form.data.question} onChange={(event) => form.setData('question', event.target.value)} />
                    {form.errors.question ? <span className="field-error" role="alert">{form.errors.question}</span> : null}
                </div>
                <div className="field">
                    <label className="label req" htmlFor="faq-answer">Answer</label>
                    <textarea id="faq-answer" className="textarea" rows={5} value={form.data.answer} onChange={(event) => form.setData('answer', event.target.value)} />
                    {form.errors.answer ? <span className="field-error" role="alert">{form.errors.answer}</span> : null}
                </div>
                <div className="grid">
                    <div className="c-12 sm-c-4 field">
                        <label className="label" htmlFor="faq-cat">Category</label>
                        <input id="faq-cat" className="input" value={form.data.category} onChange={(event) => form.setData('category', event.target.value)} />
                    </div>
                    <div className="c-12 sm-c-4 field">
                        <label className="label" htmlFor="faq-keywords">Keywords (FULLTEXT boosters)</label>
                        <input id="faq-keywords" className="input" value={form.data.keywords} onChange={(event) => form.setData('keywords', event.target.value)} />
                    </div>
                    <div className="c-12 sm-c-4 field">
                        <label className="label" htmlFor="faq-sort">Sort order</label>
                        <input id="faq-sort" className="input" type="number" min={0} value={form.data.sort_order} onChange={(event) => form.setData('sort_order', event.target.value)} />
                    </div>
                </div>
                <label className="check">
                    <input type="checkbox" checked={form.data.is_published} onChange={(event) => form.setData('is_published', event.target.checked)} />
                    <span>Published</span>
                </label>
            </form>
        </AdminModal>
    );
}

function DeleteFaqModal({ faq, onClose }: { faq: AdminFaqRow | null; onClose: () => void }) {
    return (
        <AdminModal
            open={Boolean(faq)}
            title={`Delete FAQ — ${faq?.question ?? ''}`}
            subtitle="The audit entry is retained, but the question leaves the chatbot knowledge base."
            icon="🗑"
            danger
            onClose={onClose}
            footer={<><button type="button" className="btn btn-quiet" onClick={onClose}>Cancel</button><button type="button" className="btn btn-danger" onClick={() => { if (faq) router.delete(`/admin/faqs/${faq.id}`, { preserveScroll: true, onSuccess: onClose }); }}>Delete FAQ</button></>}
        >
            <p style={{ fontSize: 14 }}>Deleting is reserved for duplicate or obsolete entries. Unpublishing keeps history and is usually safer.</p>
        </AdminModal>
    );
}

function RejectReviewModal({ review, onClose }: { review: AdminReviewRow | null; onClose: () => void }) {
    const form = useForm({ decision: 'reject', note: '' });

    useEffect(() => { if (review) { form.setData({ decision: 'reject', note: '' }); form.clearErrors(); } /* eslint-disable-next-line */ }, [review]);

    return (
        <AdminModal
            open={Boolean(review)}
            title="Hide & reject review"
            subtitle="The rating row is kept for private stats; the comment is removed from public view."
            icon="🚫"
            danger
            onClose={onClose}
            footer={<><button type="button" className="btn btn-quiet" onClick={onClose}>Cancel</button><button type="submit" form="reject-review-form" className="btn btn-danger" disabled={form.processing}>{form.processing ? 'Saving…' : 'Hide review'}</button></>}
        >
            <form id="reject-review-form" className="stack-sm" onSubmit={(event) => { event.preventDefault(); if (!review) return; form.post(`/admin/reviews/${review.id}/moderate`, { preserveScroll: true, onSuccess: onClose }); }}>
                <div className="field">
                    <label className="label req" htmlFor="review-note">Moderation note (stored, not public)</label>
                    <textarea id="review-note" className="textarea" rows={3} value={form.data.note} onChange={(event) => form.setData('note', event.target.value)} placeholder="names another patient, abusive language…" />
                    {form.errors.note ? <span className="field-error" role="alert">{form.errors.note}</span> : null}
                </div>
            </form>
        </AdminModal>
    );
}