const LOCAL_HOSTS = new Set(["localhost", "127.0.0.1", "::1"]);
const RESERVED_SUBDOMAINS = new Set(["www", "app", "api", "staging", "staging-api", "staging-tenant"]);

/** Shared login portal hosts — tenant slug comes from session after login, not hostname. */
const SHARED_TENANT_PORTAL_HOSTS = new Set([
  "dressnmore.it.com",
  "www.dressnmore.it.com",
]);

function readEnvTenantSlug(): string | null {
  const fromVite = import.meta.env.VITE_TENANT_SLUG;
  if (typeof fromVite === "string" && fromVite.trim().length > 0) {
    return fromVite.trim().toLowerCase();
  }
  return null;
}

/**
 * Resolves tenant slug from the current browser host (subdomain) or dev env fallback.
 * Does not read session state — safe for pre-login requests.
 */
export function resolveTenantSlugFromHost(): string | null {
  if (typeof window === "undefined") {
    return readEnvTenantSlug();
  }

  const hostname = window.location.hostname.toLowerCase();

  if (LOCAL_HOSTS.has(hostname) || SHARED_TENANT_PORTAL_HOSTS.has(hostname)) {
    return readEnvTenantSlug();
  }

  const parts = hostname.split(".").filter(Boolean);
  if (parts.length >= 4) {
    const subdomain = parts[0];
    if (!RESERVED_SUBDOMAINS.has(subdomain)) {
      return subdomain;
    }
  }

  return readEnvTenantSlug();
}

export function resolveTenantSlug(sessionTenantSlug?: string | null): string | null {
  if (sessionTenantSlug && sessionTenantSlug.trim().length > 0) {
    return sessionTenantSlug.trim().toLowerCase();
  }
  return resolveTenantSlugFromHost();
}

export function isSharedTenantPortalHost(): boolean {
  if (typeof window === "undefined") {
    return false;
  }

  return SHARED_TENANT_PORTAL_HOSTS.has(window.location.hostname.toLowerCase());
}
