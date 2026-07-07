export default function Chip({ label, onClick, disabled }) {
    return (
        <button
            type="button"
            onClick={onClick}
            disabled={disabled}
            className="rounded-full bg-[var(--theme-primary,#2563eb)] px-3 py-1 text-left text-xs font-medium text-white shadow-sm hover:opacity-90 disabled:opacity-50"
        >
            {label}
        </button>
    );
}
