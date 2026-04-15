/**
 * Hub path prefixes and URL helpers for the header resident switcher.
 * Keep RESIDENT_* and CLINICAL_* in sync with sidebar active state in Layout.jsx.
 */

/** Prefixes for Residents hub routes (excludes /residents hub index — handled separately). */
export const RESIDENT_HUB_PREFIXES = [
    '/my-residents',
    '/assessments',
    '/appointments',
    '/charts',
    '/t-logs',
];

/** Legacy: /residents/:id/detail (does not match /residents/sign-out). */
export const RESIDENT_LEGACY_DETAIL = /^\/residents\/[^/]+\/detail(?:\/|$)/;

/** Prefixes for Clinical hub (excludes /clinical index — handled separately). */
export const CLINICAL_HUB_PREFIXES = [
    '/vitals',
    '/view-vitals',
    '/medication-history',
    '/sleep',
    '/sleep-patterns',
    '/medications',
    '/medication-deliveries',
];

const RESIDENTS_MANAGEMENT_PATH_PREFIXES = ['/residents/sign-out', '/residents/sign-outs'];

function pathnameMatchesPrefix(pathname, prefix) {
    return pathname === prefix || pathname.startsWith(`${prefix}/`);
}

function isResidentsSectionForSwitcher(pathname) {
    if (pathname === '/residents') return true;
    if (RESIDENTS_MANAGEMENT_PATH_PREFIXES.some(p => pathname === p || pathname.startsWith(`${p}/`))) {
        return false;
    }
    if (pathname.startsWith('/residents/')) return true;
    return RESIDENT_HUB_PREFIXES.some(p => pathnameMatchesPrefix(pathname, p));
}

function isClinicalSectionForSwitcher(pathname) {
    if (pathname === '/clinical') return true;
    return CLINICAL_HUB_PREFIXES.some(p => pathnameMatchesPrefix(pathname, p));
}

/**
 * Whether the compact resident avatar strip should appear in the app header.
 */
export function shouldShowHeaderResidentSwitcher(pathname) {
    if (!pathname) return false;
    return isResidentsSectionForSwitcher(pathname) || isClinicalSectionForSwitcher(pathname);
}

const RE_MY_RESIDENTS = /^\/my-residents\/([^/]+)/;
const RE_RESIDENTS_DETAIL = /^\/residents\/([^/]+)\/detail/;
const RE_CHARTS_RESIDENT = /^\/charts\/resident\/([^/]+)/;
const RE_APPT_CREATE = /^\/appointments\/create\/([^/]+)/;

/**
 * Resident id from URL when the current route is scoped to one resident, else null.
 */
export function parseResidentIdFromPath(pathname) {
    if (!pathname) return null;
    let m = pathname.match(RE_MY_RESIDENTS);
    if (m) return m[1];
    m = pathname.match(RE_RESIDENTS_DETAIL);
    if (m) return m[1];
    m = pathname.match(RE_CHARTS_RESIDENT);
    if (m) return m[1];
    m = pathname.match(RE_APPT_CREATE);
    if (m) return m[1];
    return null;
}

function defaultTabForPathWhenSwitchingToHub(pathname) {
    if (!pathname) return null;
    if (pathname.startsWith('/medications') || pathname.startsWith('/medication-history') || pathname.startsWith('/medication-deliveries')) {
        return 'medications';
    }
    if (pathname.startsWith('/vitals') || pathname.startsWith('/view-vitals')) {
        return 'vitals';
    }
    return null;
}

/**
 * Target path + query when choosing a resident from the header strip.
 * @param {string} pathname
 * @param {string} search - location.search including leading ?
 * @param {string} newResidentId
 * @returns {string}
 */
export function buildSwitchHref(pathname, search, newResidentId) {
    const id = String(newResidentId);

    if (pathname.match(RE_MY_RESIDENTS)) {
        return `/my-residents/${id}${search || ''}`;
    }
    if (pathname.match(RE_RESIDENTS_DETAIL)) {
        return `/residents/${id}/detail${search || ''}`;
    }
    if (pathname.match(RE_CHARTS_RESIDENT)) {
        return `/charts/resident/${id}${search || ''}`;
    }
    if (pathname.match(RE_APPT_CREATE)) {
        return `/appointments/create/${id}${search || ''}`;
    }

    const tab = defaultTabForPathWhenSwitchingToHub(pathname);
    if (tab) {
        return `/my-residents/${id}?tab=${encodeURIComponent(tab)}`;
    }
    return `/my-residents/${id}`;
}
