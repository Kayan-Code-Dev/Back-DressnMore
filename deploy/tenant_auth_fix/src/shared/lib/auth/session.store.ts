import { hasAnyPermission } from "@/lib/tenant-permissions";
import { useSyncExternalStore } from "react";
import { storage } from "@/shared/lib/storage/storage";
import type { TenantSubscription } from "@/features/subscriptions/types/subscription.types";
import type { AppSettings } from "@/shared/lib/format/money";
import { DEFAULT_APP_SETTINGS } from "@/shared/lib/format/money";
import { isSharedTenantPortalHost, resolveTenantSlugFromHost } from "@/shared/lib/tenant/tenant-context";
import {
  LEGACY_SESSION_KEY,
  tenantStorageKey,
} from "@/shared/lib/tenant/tenant-storage";

export type SessionState = {
  token: string | null;
  tenant: unknown | null;
  user: unknown | null;
  permissions: string[];
  subscription: TenantSubscription | null;
  appSettings: AppSettings | null;
};

export type SessionPayload = Omit<SessionState, "appSettings"> & {
  appSettings?: AppSettings | null;
};

type SessionStoreShape = {
  getState: () => SessionState;
  setSession: (payload: SessionPayload) => void;
  setAppSettings: (appSettings: AppSettings) => void;
  clearSession: () => void;
  hasPermission: (permission: string) => boolean;
  isPlanModuleEnabled: (planKey: string) => boolean;
  isAuthenticated: () => boolean;
  getTenantSlug: () => string | null;
  subscribe: (listener: () => void) => () => void;
};

const defaultState: SessionState = {
  token: null,
  tenant: null,
  user: null,
  permissions: [],
  subscription: null,
  appSettings: null,
};

const listeners = new Set<() => void>();

const emit = () => {
  for (const listener of listeners) {
    listener();
  }
};

function readTenantSlug(tenant: unknown): string | null {
  if (tenant && typeof tenant === "object" && "slug" in tenant && typeof tenant.slug === "string") {
    return tenant.slug.trim().toLowerCase();
  }
  return null;
}

function findScopedSessionKey(): string | null {
  if (typeof window === "undefined") {
    return null;
  }

  try {
    const keys = Object.keys(window.localStorage);
    return keys.find((key) => /^dressnmore:[^:]+:session$/.test(key)) ?? null;
  } catch {
    return null;
  }
}

function loadPersistedSession(): SessionState {
  const hostSlug = resolveTenantSlugFromHost();
  const sharedPortal = isSharedTenantPortalHost();

  if (hostSlug && !sharedPortal) {
    const scoped = storage.get<SessionState>(tenantStorageKey(hostSlug, "session"), defaultState);
    if (scoped.token) {
      return { ...defaultState, ...scoped, appSettings: scoped.appSettings ?? null };
    }
  }

  const scopedSessionKey = findScopedSessionKey();
  if (scopedSessionKey) {
    const scoped = storage.get<SessionState>(scopedSessionKey, defaultState);
    if (scoped.token) {
      return { ...defaultState, ...scoped, appSettings: scoped.appSettings ?? null };
    }
  }

  const legacy = storage.get<SessionState>(LEGACY_SESSION_KEY, defaultState);
  if (!legacy.token) {
    return defaultState;
  }

  const legacySlug = readTenantSlug(legacy.tenant);
  if (hostSlug && legacySlug && legacySlug !== hostSlug) {
    return defaultState;
  }

  if (hostSlug && legacySlug === hostSlug) {
    storage.set(tenantStorageKey(hostSlug, "session"), legacy);
    storage.remove(LEGACY_SESSION_KEY);
  }

  return { ...defaultState, ...legacy, appSettings: legacy.appSettings ?? null };
}

let state: SessionState = loadPersistedSession();

function persistSession(next: SessionState): void {
  const slug = readTenantSlug(next.tenant) ?? resolveTenantSlugFromHost();
  if (slug) {
    storage.set(tenantStorageKey(slug, "session"), next);
    storage.set(LEGACY_SESSION_KEY, next);
    return;
  }

  storage.set(LEGACY_SESSION_KEY, next);
}

function removePersistedSession(): void {
  const slug = readTenantSlug(state.tenant) ?? resolveTenantSlugFromHost();
  if (slug) {
    storage.remove(tenantStorageKey(slug, "session"));
  }
  storage.remove(LEGACY_SESSION_KEY);
}

export const sessionStore: SessionStoreShape = {
  getState: () => state,

  setSession: (payload) => {
    state = {
      token: payload.token,
      tenant: payload.tenant,
      user: payload.user,
      permissions: [...payload.permissions],
      subscription: payload.subscription,
      appSettings: payload.appSettings ?? state.appSettings ?? DEFAULT_APP_SETTINGS,
    };
    persistSession(state);
    emit();
  },

  setAppSettings: (appSettings) => {
    state = {
      ...state,
      appSettings,
    };
    persistSession(state);
    emit();
  },

  clearSession: () => {
    state = { ...defaultState };
    removePersistedSession();
    emit();
  },

  hasPermission: (permission) => {
    if (!permission) return true;
    return hasAnyPermission(state.permissions, permission);
  },

  isPlanModuleEnabled: (planKey) => {
    const modules = state.subscription?.enabled_modules;
    if (modules === undefined) {
      return true;
    }

    return modules.includes(planKey);
  },

  isAuthenticated: () => state.token !== null,

  getTenantSlug: () => readTenantSlug(state.tenant),

  subscribe: (listener) => {
    listeners.add(listener);
    return () => listeners.delete(listener);
  },
};

export function useSession<T>(selector: (value: SessionState) => T): T {
  return useSyncExternalStore(
    sessionStore.subscribe,
    () => selector(sessionStore.getState()),
    () => selector(defaultState),
  );
}
