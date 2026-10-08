import React from 'react';
import { MoreVertical, Trash2 } from 'lucide-react';
import DropdownMenu, { DropdownMenuItem } from '../ui/radix/DropdownMenu';

/**
 * Overflow menu for the rarely used, destructive resident actions.
 * Keeps permanent delete off the face of the card so it cannot be hit by accident.
 *
 * The trigger is a plain button rather than CardIconButton because Radix needs to
 * forward a ref onto it.
 */
export default function ResidentActionsMenu({ onDelete }) {
    return (
        <DropdownMenu
            align="end"
            trigger={
                <button
                    type="button"
                    aria-label="More resident actions"
                    className="rounded-lg border border-slate-200 bg-white p-2 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-[var(--theme-primary)] disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <MoreVertical className="h-4 w-4 text-slate-600" strokeWidth={2.5} />
                </button>
            }
        >
            <DropdownMenuItem
                className="!text-red-600 hover:!bg-red-50 focus:!bg-red-50"
                onSelect={() => onDelete()}
            >
                <Trash2 className="mr-2 h-4 w-4" />
                Delete permanently
            </DropdownMenuItem>
        </DropdownMenu>
    );
}
