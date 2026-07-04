export default function Chip({ label, onClick, disabled }) {
    return (
        <button
            type="button"
            onClick={onClick}
            disabled={disabled}
            className="rounded-full border border-[var(--theme-primary,#2563eb)] px-3 py-1 text-xs font-medium text-[var(--theme-primary,#2563eb)] hover:bg-[var(--theme-primary,#2563eb)] hover:text-white disabled:opacity-50"
        >
            {label}
        </button>
    );
}
