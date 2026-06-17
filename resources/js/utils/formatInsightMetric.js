export function formatInsightPercent(value) {
    if (value == null || Number.isNaN(Number(value))) {
        return '—';
    }

    return `${Number(value).toFixed(1)}%`;
}

export function formatInsightHours(value) {
    if (value == null || Number.isNaN(Number(value))) {
        return '—';
    }

    return `${Number(value).toFixed(1)} hrs`;
}
