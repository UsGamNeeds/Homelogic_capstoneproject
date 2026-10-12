import React from 'react';
import { AlertTriangle } from 'lucide-react';
import ConfirmDialog from '../ui/ConfirmDialog';

export const DELETE_CONFIRM_WORD = 'DELETE';

export function residentDisplayName(resident) {
    if (!resident) return 'this resident';
    const fullName = [resident.first_name, resident.middle_names, resident.last_name].filter(Boolean).join(' ');
    return fullName || resident.name || 'this resident';
}

/**
 * Permanent (non-recoverable) resident deletion confirmation.
 * Requires the user to type DELETE so a record is never wiped by a stray click.
 */
export default function DeleteResidentDialog({
    resident,
    isOpen,
    onClose,
    onConfirm,
    isPending = false,
    error = null,
    confirmText,
    onConfirmTextChange,
}) {
    const name = residentDisplayName(resident);
    const canConfirm = confirmText.trim().toUpperCase() === DELETE_CONFIRM_WORD;

    return (
        <ConfirmDialog
            isOpen={isOpen}
            onClose={onClose}
            onConfirm={() => {
                if (canConfirm) onConfirm();
            }}
            title="Permanently delete resident"
            variant="danger"
            confirmLabel="Delete permanently"
            cancelLabel="Cancel"
            isPending={isPending}
            confirmDisabled={!canConfirm}
            description={
                <div className="space-y-4">
                    <div className="flex gap-3 rounded-xl border border-red-200 bg-red-50 p-4">
                        <AlertTriangle className="mt-0.5 h-5 w-5 flex-shrink-0 text-red-600" />
                        <div className="space-y-1 text-sm text-red-800">
                            <p className="font-semibold">This cannot be undone.</p>
                            <p>
                                <span className="font-semibold">{name}</span> and everything recorded for them —
                                medications, vitals, assessments, appointments, incidents, sign-outs, documents and
                                notes — will be permanently removed from the system.
                            </p>
                        </div>
                    </div>
                    <p className="text-sm text-slate-600">
                        If you only want to stop this resident appearing in day-to-day care, change their status to
                        discharged instead of deleting them.
                    </p>
                    <div>
                        <label htmlFor="delete-resident-confirm" className="block text-sm font-medium text-slate-700">
                            Type <span className="font-bold">{DELETE_CONFIRM_WORD}</span> to confirm
                        </label>
                        <input
                            id="delete-resident-confirm"
                            type="text"
                            autoComplete="off"
                            value={confirmText}
                            disabled={isPending}
                            onChange={(e) => onConfirmTextChange(e.target.value)}
                            placeholder={DELETE_CONFIRM_WORD}
                            className="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-transparent focus:ring-2 focus:ring-red-500 disabled:bg-slate-100"
                        />
                    </div>
                    {error && (
                        <p className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">
                            {error.response?.data?.message || error.message || 'Failed to delete resident.'}
                        </p>
                    )}
                </div>
            }
        >
            {!canConfirm && (
                <p className="text-xs text-slate-500">
                    The delete button stays disabled until {DELETE_CONFIRM_WORD} is typed above.
                </p>
            )}
        </ConfirmDialog>
    );
}
