import { AUTH_TOKEN_KEY } from '../lib/session';
import { getApiBase } from '../config/api.config';

type FetchOptions = RequestInit & { skipAuth?: boolean };

const inFlight = new Map<string, Promise<Response>>();

function buildDedupeKey(method: string, url: string, body: BodyInit | null | undefined): string {
  const m = method.toUpperCase();
  if (m === 'GET' || m === 'HEAD') {
    return `${m} ${url}`;
  }
  if (typeof body === 'string') {
    return `${m} ${url} ${body}`;
  }
  if (body == null || body === undefined) {
    return `${m} ${url}`;
  }
  return '';
}

/**
 * Authenticated fetch to the API base.
 * Identical concurrent requests share one network call; each caller gets `response.clone()`.
 */
export async function apiFetch(path: string, options: FetchOptions = {}): Promise<Response> {
  const { skipAuth, headers: initHeaders, ...rest } = options;
  const base = getApiBase();
  const url = path.startsWith('http') ? path : `${base}${path.startsWith('/') ? '' : '/'}${path}`;

  const headers = new Headers(initHeaders);
  if (!headers.has('Accept')) headers.set('Accept', 'application/json');

  if (!skipAuth) {
    const token = localStorage.getItem(AUTH_TOKEN_KEY);
    if (token) headers.set('Authorization', `Bearer ${token}`);
  }

  if (rest.signal) {
    return fetch(url, { ...rest, headers });
  }

  const method = (rest.method ?? 'GET').toUpperCase();
  const body = rest.body;

  const key =
    typeof body === 'string' || body == null
      ? buildDedupeKey(method, url, body ?? null)
      : '';

  if (!key) {
    return fetch(url, { ...rest, headers });
  }

  let entry = inFlight.get(key);
  if (!entry) {
    entry = fetch(url, { ...rest, headers });
    inFlight.set(key, entry);
    entry.finally(() => {
      if (inFlight.get(key) === entry) {
        inFlight.delete(key);
      }
    });
  }

  const res = await entry;
  return res.clone();
}
