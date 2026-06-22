// URL sanitizers — block javascript:, data:, vbscript: and other dangerous
// schemes before a URL reaches an <a href>, <img src>, window.location, or
// the router. API/websocket-sourced URLs must pass through these.

const SAFE_LINK_SCHEMES = ['http:', 'https:', 'mailto:', 'tel:'];

/**
 * Return the lowercase scheme (e.g. "javascript:") of a URL string, or ''
 * when the URL is relative / a fragment (no scheme).
 */
function schemeOf(raw) {
  const match = /^\s*([a-z][a-z0-9+.-]*):/i.exec(String(raw ?? ''));
  return match ? `${match[1].toLowerCase()}:` : '';
}

/**
 * Sanitize a URL bound for an <a href> or <img src>. Allows http(s), mailto,
 * tel, and relative URLs; rejects javascript:, data:, vbscript:, etc.
 * Returns `fallback` (default undefined) for anything unsafe.
 */
export function safeHref(raw, fallback = undefined) {
  if (raw == null || raw === '') return fallback;
  const scheme = schemeOf(raw);
  if (scheme === '') return raw; // relative URL or fragment — safe
  return SAFE_LINK_SCHEMES.includes(scheme) ? raw : fallback;
}

/**
 * Sanitize a URL used to programmatically redirect (window.location) or
 * navigate. Allows only http(s) absolute URLs or in-app relative paths.
 * Rejects non-http(s) schemes (javascript:, data:) and protocol-relative
 * ('//host') external redirects. Returns `fallback` (default '/') if unsafe.
 */
export function safeRedirect(raw, fallback = '/') {
  if (raw == null || raw === '') return fallback;
  const value = String(raw);
  if (value.startsWith('//')) return fallback; // protocol-relative → external
  const scheme = schemeOf(value);
  if (scheme === '') {
    return value.startsWith('/') ? value : `/${value}`;
  }
  return scheme === 'http:' || scheme === 'https:' ? value : fallback;
}
