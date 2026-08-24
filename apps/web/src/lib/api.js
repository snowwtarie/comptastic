// apps/web/src/lib/api.js
const BASE = import.meta.env.VITE_API_BASE_URL;

export class ApiError extends Error {
  constructor(status, body) {
    super(body?.message || 'Request failed');
    this.status = status;
    this.errors = body?.errors ?? null; // Laravel 422 validation shape: { field: [msg, ...] }
  }
}

let csrfPrimed = false;
let onUnauthorized = null;

export function registerUnauthorizedHandler(fn) {
  onUnauthorized = fn;
}

export function resetCsrf() {
  csrfPrimed = false;
}

export function extractErrorMessage(e) {
  return e instanceof ApiError ? (e.errors ? Object.values(e.errors).flat()[0] : e.message) : 'Une erreur est survenue.';
}

async function primeCsrf() {
  if (csrfPrimed) return;
  await fetch(`${BASE}/sanctum/csrf-cookie`, { credentials: 'include' });
  csrfPrimed = true;
}

function xsrfTokenFromCookie() {
  const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
  return match ? decodeURIComponent(match[1]) : '';
}

export async function apiFetch(path, { method = 'GET', body } = {}) {
  if (method !== 'GET') await primeCsrf();

  const res = await fetch(`${BASE}${path}`, {
    method,
    credentials: 'include',
    headers: {
      Accept: 'application/json',
      ...(body ? { 'Content-Type': 'application/json' } : {}),
      ...(method !== 'GET' ? { 'X-XSRF-TOKEN': xsrfTokenFromCookie() } : {}),
    },
    body: body ? JSON.stringify(body) : undefined,
  });

  if (res.status === 401) {
    const parsedBody = await res.json().catch(() => null);
    onUnauthorized?.();
    throw new ApiError(401, parsedBody);
  }
  if (!res.ok) {
    throw new ApiError(res.status, await res.json().catch(() => null));
  }
  if (res.status === 204) return null;
  return res.json();
}
