const isoDatePattern = /^(\d{4})-(\d{2})-(\d{2})$/;

function parseDate(value) {
    const date = new Date(value.includes('T') ? value : `${value}T00:00:00`);
    return Number.isNaN(date.getTime()) ? null : date;
}

export function formatIsoDateOnly(value) {
    const match = String(value || '').match(isoDatePattern);
    if (! match) return null;
    return `${match[3]}/${match[2]}/${match[1]}`;
}

export function formatDate(value) {
    if (! value) return '';
    if (! String(value).includes('T')) {
        const isoFormatted = formatIsoDateOnly(value);
        if (isoFormatted) return isoFormatted;
    }
    const date = parseDate(String(value));
    if (! date) return '';
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    return `${day}/${month}/${year}`;
}

export function displayToIso(value) {
    const match = String(value || '').trim().match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
    if (! match) return null;

    const day = Number(match[1]);
    const month = Number(match[2]);
    const year = Number(match[3]);
    const iso = `${String(year).padStart(4, '0')}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    const parsed = parseDate(iso);

    if (! parsed || parsed.getFullYear() !== year || parsed.getMonth() + 1 !== month || parsed.getDate() !== day) {
        return null;
    }

    return iso;
}

export function formatDateRange(from, to) {
    if (! from && ! to) return '';
    if (from && to) return `${formatDate(from)} – ${formatDate(to)}`;
    if (from) return `From ${formatDate(from)}`;
    return `Until ${formatDate(to)}`;
}
