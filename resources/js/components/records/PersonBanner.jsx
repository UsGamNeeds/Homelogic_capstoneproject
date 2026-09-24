import React from 'react';
import { useNavigate } from 'react-router-dom';
import { ChevronLeft, User } from 'lucide-react';

/**
 * Prominent banner shown at the top of a "step 2" add-record page, naming
 * the resident/staff member the record is being created for. Pairs with
 * ResidentPickerPage / StaffPickerPage as step 1 of the add flow.
 */
export default function PersonBanner({ label = 'Adding record for', name, meta, changeTo, isLoading = false }) {
    const navigate = useNavigate();

    return (
        <header
            className="rounded-2xl p-6 text-white shadow-lg"
            style={{ background: `linear-gradient(to right, var(--theme-primary), var(--theme-primary-hover), var(--theme-primary))` }}
        >
            <div className="flex items-start justify-between gap-4">
                <div className="flex items-start gap-3">
                    {changeTo && (
                        <button
                            type="button"
                            onClick={() => navigate(changeTo)}
                            className="mt-1 inline-flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-white/10 text-white hover:bg-white/20"
                            aria-label="Back to selection"
                        >
                            <ChevronLeft className="h-5 w-5" />
                        </button>
                    )}
                    <div>
                        <p className="text-sm font-medium uppercase tracking-wide opacity-90">{label}</p>
                        <h1 className="flex items-center gap-2 text-2xl font-semibold md:text-3xl">
                            <User className="h-6 w-6 flex-shrink-0 opacity-90" />
                            {isLoading ? 'Loading…' : name || 'Unknown'}
                        </h1>
                        {meta && <p className="mt-2 max-w-xl text-sm opacity-90">{meta}</p>}
                    </div>
                </div>
                {changeTo && (
                    <button
                        type="button"
                        onClick={() => navigate(changeTo)}
                        className="flex-shrink-0 rounded-lg bg-white/10 px-3 py-1.5 text-xs font-semibold text-white hover:bg-white/20"
                    >
                        Change
                    </button>
                )}
            </div>
        </header>
    );
}
