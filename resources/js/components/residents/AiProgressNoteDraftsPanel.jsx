import React, { useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { Wand2, Loader2, AlertTriangle, CheckCircle2, XCircle, Tag, Clock } from 'lucide-react';
import api from '../../services/api';
import useCurrentUser from '../../hooks/useCurrentUser';

const STATUS_STYLES = {
    pending_review: { label: 'Pending review', className: 'bg-gray-100 text-gray-700' },
    flagged: { label: 'Flagged for review', className: 'bg-amber-100 text-amber-800' },
    approved: { label: 'Approved', className: 'bg-green-100 text-green-800' },
    rejected: { label: 'Rejected', className: 'bg-red-100 text-red-700' },
};

const SEVERITY_STYLES = {
    high: 'border-red-200 bg-red-50 text-red-800',
    medium: 'border-amber-200 bg-amber-50 text-amber-800',
    low: 'border-gray-200 bg-gray-50 text-gray-700',
};

function StatusBadge({ status }) {
    const style = STATUS_STYLES[status] || STATUS_STYLES.pending_review;
    return (
        <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${style.className}`}>
            {style.label}
        </span>
    );
}

function DraftCard({ draft, canReview, onApprove, onReject, busy }) {
    const [narrative, setNarrative] = useState(draft.draft_json?.narrative || '');
    const [reviewNotes, setReviewNotes] = useState('');
    const [confirmChecked, setConfirmChecked] = useState(false);
    const [showReject, setShowReject] = useState(false);

    const events = draft.draft_json?.events || [];
    const medications = draft.draft_json?.medications || [];
    const flags = draft.summary_json?.flags || [];

    return (
        <div className="rounded-xl border border-gray-200 bg-white p-4 space-y-3 shadow-sm">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="flex items-center gap-2">
                    <StatusBadge status={draft.status} />
                    <span className="text-xs text-gray-500 flex items-center gap-1">
                        <Clock className="h-3.5 w-3.5" />
                        {new Date(draft.created_at).toLocaleString()}
                    </span>
                </div>
                <span className="text-xs text-gray-400">
                    Mode: {draft.mode}{draft.model ? ` (${draft.model})` : ''}
                </span>
            </div>

            <div className="rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 text-xs font-semibold text-amber-800">
                Draft – human review required. Nothing here is a real progress note until approved below.
            </div>

            <div>
                <h4 className="text-sm font-semibold text-gray-900 mb-1">Drafted narrative</h4>
                {canReview && draft.status !== 'approved' && draft.status !== 'rejected' ? (
                    <textarea
                        className="w-full rounded-lg border border-gray-300 p-2 text-sm text-gray-800"
                        rows={4}
                        value={narrative}
                        onChange={(e) => setNarrative(e.target.value)}
                    />
                ) : (
                    <p className="text-sm text-gray-700 whitespace-pre-wrap">{draft.draft_json?.narrative}</p>
                )}
            </div>

            {events.length > 0 && (
                <div>
                    <h4 className="text-sm font-semibold text-gray-900 mb-1">Events</h4>
                    <ul className="space-y-1">
                        {events.map((ev, i) => (
                            <li key={i} className="text-sm text-gray-700 flex items-start gap-2">
                                <Tag className="h-3.5 w-3.5 mt-0.5 text-gray-400 shrink-0" />
                                <span>
                                    {ev.text}
                                    <span className="ml-1 text-xs text-gray-400">[{ev.source}]</span>
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            <div>
                <h4 className="text-sm font-semibold text-gray-900 mb-1">Medications referenced</h4>
                {medications.length === 0 ? (
                    <p className="text-xs text-gray-500">Not documented — no verified medication mentions in this draft.</p>
                ) : (
                    <ul className="space-y-1">
                        {medications.map((m, i) => (
                            <li key={i} className="text-sm text-gray-700 flex items-center gap-2">
                                <Tag className="h-3.5 w-3.5 text-gray-400 shrink-0" />
                                {m.name}
                                {m.rxnorm_cui && <span className="text-xs text-gray-400">RxNorm {m.rxnorm_cui}</span>}
                                <span className="text-xs text-gray-400">[{m.source}]</span>
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            <div>
                <h4 className="text-sm font-semibold text-gray-900 mb-1">7-day follow-up summary</h4>
                <p className="text-sm text-gray-700">{draft.summary_json?.text}</p>
            </div>

            {flags.length > 0 && (
                <div className="space-y-1.5">
                    <h4 className="text-sm font-semibold text-gray-900 flex items-center gap-1">
                        <AlertTriangle className="h-4 w-4 text-amber-600" /> Flags ({flags.length})
                    </h4>
                    {flags.map((f, i) => (
                        <div key={i} className={`rounded-lg border px-2.5 py-1.5 text-xs ${SEVERITY_STYLES[f.severity] || SEVERITY_STYLES.low}`}>
                            {f.detail}
                        </div>
                    ))}
                </div>
            )}

            {canReview && draft.status !== 'approved' && draft.status !== 'rejected' && (
                <div className="border-t border-gray-100 pt-3 space-y-2">
                    <label className="flex items-center gap-2 text-xs text-gray-600">
                        <input
                            type="checkbox"
                            checked={confirmChecked}
                            onChange={(e) => setConfirmChecked(e.target.checked)}
                        />
                        I reviewed this draft and confirm it is accurate before saving it as a progress note.
                    </label>

                    <div className="flex flex-wrap gap-2">
                        <button
                            type="button"
                            disabled={!confirmChecked || busy}
                            onClick={() => onApprove(draft, narrative)}
                            className="inline-flex items-center gap-1.5 rounded-lg bg-green-600 px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-40 hover:bg-green-700"
                        >
                            <CheckCircle2 className="h-3.5 w-3.5" /> Approve & save as progress note
                        </button>
                        <button
                            type="button"
                            disabled={busy}
                            onClick={() => setShowReject((s) => !s)}
                            className="inline-flex items-center gap-1.5 rounded-lg border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50"
                        >
                            <XCircle className="h-3.5 w-3.5" /> Reject
                        </button>
                    </div>

                    {showReject && (
                        <div className="space-y-2">
                            <textarea
                                className="w-full rounded-lg border border-gray-300 p-2 text-xs"
                                rows={2}
                                placeholder="Why is this draft being rejected? (required)"
                                value={reviewNotes}
                                onChange={(e) => setReviewNotes(e.target.value)}
                            />
                            <button
                                type="button"
                                disabled={!reviewNotes.trim() || busy}
                                onClick={() => onReject(draft, reviewNotes)}
                                className="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-40 hover:bg-red-700"
                            >
                                Confirm rejection
                            </button>
                        </div>
                    )}
                </div>
            )}

            {draft.status === 'approved' && (
                <p className="text-xs text-green-700">Saved as a progress note (T-Logs) on {new Date(draft.reviewed_at).toLocaleString()}.</p>
            )}
            {draft.status === 'rejected' && (
                <p className="text-xs text-red-700">Rejected: {draft.review_notes}</p>
            )}
        </div>
    );
}

export default function AiProgressNoteDraftsPanel({ residentId }) {
    const queryClient = useQueryClient();
    const { user } = useCurrentUser();
    const canReview = user && ['administrator', 'super_admin'].includes(user.role);

    const [shiftEntry, setShiftEntry] = useState('');
    const [generating, setGenerating] = useState(false);
    const [busyDraftId, setBusyDraftId] = useState(null);
    const [error, setError] = useState('');

    const draftsQuery = useQuery({
        queryKey: ['ai-progress-notes', residentId],
        queryFn: async () => {
            const res = await api.get(`/residents/${residentId}/ai-progress-notes`);
            return res.data?.drafts || [];
        },
        enabled: !!residentId,
    });

    const generateDraft = async () => {
        setGenerating(true);
        setError('');
        try {
            await api.post(`/residents/${residentId}/ai-progress-notes`, {
                shift_entry: shiftEntry.trim() || null,
            });
            setShiftEntry('');
            queryClient.invalidateQueries({ queryKey: ['ai-progress-notes', residentId] });
        } catch (e) {
            setError(e?.response?.data?.message || 'Could not generate a draft right now.');
        } finally {
            setGenerating(false);
        }
    };

    const handleApprove = async (draft, narrative) => {
        setBusyDraftId(draft.id);
        setError('');
        try {
            await api.post(`/residents/${residentId}/ai-progress-notes/${draft.id}/approve`, {
                confirmed: true,
                narrative,
            });
            queryClient.invalidateQueries({ queryKey: ['ai-progress-notes', residentId] });
        } catch (e) {
            setError(e?.response?.data?.message || 'Could not approve this draft.');
        } finally {
            setBusyDraftId(null);
        }
    };

    const handleReject = async (draft, reviewNotes) => {
        setBusyDraftId(draft.id);
        setError('');
        try {
            await api.post(`/residents/${residentId}/ai-progress-notes/${draft.id}/reject`, {
                review_notes: reviewNotes,
            });
            queryClient.invalidateQueries({ queryKey: ['ai-progress-notes', residentId] });
        } catch (e) {
            setError(e?.response?.data?.message || 'Could not reject this draft.');
        } finally {
            setBusyDraftId(null);
        }
    };

    const drafts = draftsQuery.data || [];

    return (
        <div className="space-y-4">
            <div className="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <h2 className="text-lg font-semibold text-gray-900 flex items-center gap-2">
                        <Wand2 className="h-5 w-5 text-[var(--theme-primary)]" />
                        AI Draft Notes
                    </h2>
                    <p className="text-sm text-gray-500 max-w-2xl">
                        Paste a rough shift entry to draft a progress note and a 7-day follow-up summary. This is a
                        separate module from T-Logs — nothing here is saved or acted on until a reviewer approves it.
                    </p>
                </div>
            </div>

            <div className="rounded-xl border border-gray-200 bg-white p-4 space-y-3">
                <textarea
                    className="w-full rounded-lg border border-gray-300 p-3 text-sm"
                    rows={4}
                    placeholder="Rough shift entry (optional — leave blank to generate a 7-day summary only)..."
                    value={shiftEntry}
                    onChange={(e) => setShiftEntry(e.target.value)}
                />
                <button
                    type="button"
                    onClick={generateDraft}
                    disabled={generating}
                    className="inline-flex items-center gap-2 rounded-lg border-2 border-[var(--theme-primary)] bg-[var(--theme-primary)] px-4 py-2 text-sm font-semibold text-[var(--theme-text-on-primary)] hover:bg-[var(--theme-primary-hover)] disabled:opacity-50"
                >
                    {generating ? <Loader2 className="h-4 w-4 animate-spin" /> : <Wand2 className="h-4 w-4" />}
                    Generate draft
                </button>
                {error && <p className="text-sm text-red-600">{error}</p>}
            </div>

            {!canReview && (
                <p className="text-xs text-gray-400">Only administrators can approve or reject drafts; you can still generate them for review.</p>
            )}

            {draftsQuery.isLoading ? (
                <p className="text-sm text-gray-500">Loading drafts…</p>
            ) : drafts.length === 0 ? (
                <p className="text-sm text-gray-500">No AI drafts yet for this resident.</p>
            ) : (
                <div className="space-y-4">
                    {drafts.map((draft) => (
                        <DraftCard
                            key={draft.id}
                            draft={draft}
                            canReview={canReview}
                            onApprove={handleApprove}
                            onReject={handleReject}
                            busy={busyDraftId === draft.id}
                        />
                    ))}
                </div>
            )}
        </div>
    );
}
