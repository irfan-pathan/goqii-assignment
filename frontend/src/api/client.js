const BASE = '/api';

// The access token lives only in memory (not localStorage) so XSS cannot read it from storage.
// A page reload restores the session through the httpOnly refresh cookie instead.
let accessToken = null;
let refreshPromise = null;
let onSessionLost = () => {};

export class ApiError extends Error {
  constructor(status, message, errors = {}) {
    super(message);
    this.status = status;
    this.errors = errors; // field -> message, from 422 responses
  }
}

export const setSessionLostHandler = (fn) => {
  onSessionLost = fn;
};

async function send(path, { method = 'GET', body, token } = {}) {
  let res;
  try {
    res = await fetch(BASE + path, {
      method,
      credentials: 'include',
      headers: {
        ...(body !== undefined && { 'Content-Type': 'application/json' }),
        ...(token && { Authorization: `Bearer ${token}` }),
      },
      body: body !== undefined ? JSON.stringify(body) : undefined,
    });
  } catch {
    throw new ApiError(0, 'Cannot reach the server. Check your connection.');
  }
  if (res.status === 204) return null;
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new ApiError(res.status, data.error || 'Request failed.', data.errors);
  return data;
}

/** Exchange the refresh cookie for a new access token. Concurrent callers share one request. */
export function refreshSession() {
  refreshPromise ??= send('/auth/refresh', { method: 'POST' })
    .then((data) => {
      accessToken = data.access_token;
      return data;
    })
    .finally(() => {
      refreshPromise = null;
    });
  return refreshPromise;
}

export async function authenticate(path, body) {
  const data = await send(path, { method: 'POST', body });
  accessToken = data.access_token;
  return data;
}

export async function logoutRequest() {
  try {
    await send('/auth/logout', { method: 'POST' });
  } finally {
    accessToken = null;
  }
}

/** Authenticated request: on 401 it refreshes the token once and retries. */
export async function api(path, options = {}) {
  try {
    return await send(path, { ...options, token: accessToken });
  } catch (err) {
    if (err.status !== 401) throw err;
    try {
      await refreshSession();
    } catch {
      accessToken = null;
      onSessionLost();
      throw err;
    }
    return send(path, { ...options, token: accessToken });
  }
}
