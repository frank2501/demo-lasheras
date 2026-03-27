'use client';

import { useState, useEffect } from 'react';
import { getConfig } from '@/lib/api';
import type { ArtechiaConfig } from '@/types/config';

const CACHE_KEY = 'artechia_config';
const CACHE_TTL_MS = 5 * 60 * 1000; // 5 minutes

interface CachedConfig {
  data: ArtechiaConfig;
  ts: number;
}

function readCache(): ArtechiaConfig | null {
  try {
    const raw = sessionStorage.getItem(CACHE_KEY);
    if (!raw) return null;
    const { data, ts }: CachedConfig = JSON.parse(raw);
    if (Date.now() - ts > CACHE_TTL_MS) {
      sessionStorage.removeItem(CACHE_KEY);
      return null;
    }
    return data;
  } catch {
    return null;
  }
}

function writeCache(data: ArtechiaConfig): void {
  try {
    const entry: CachedConfig = { data, ts: Date.now() };
    sessionStorage.setItem(CACHE_KEY, JSON.stringify(entry));
  } catch {
    // sessionStorage might be unavailable (e.g. SSR guard) — silently ignore
  }
}

/**
 * Default config used as fallback if the endpoint is unreachable.
 * Mirrors the plugin's default values so the UI is never broken.
 */
const DEFAULT_CONFIG: ArtechiaConfig = {
  payment_methods: [
    {
      id: 'mercadopago',
      label: 'MercadoPago',
      description: 'Tarjeta de débito, crédito o efectivo',
      icon: 'mercadopago',
    },
  ],
  currency: 'ARS',
  currency_symbol: '$',
  currency_position: 'before',
  decimal_separator: ',',
  thousand_separator: '.',
  decimals: 0,
  date_format: 'd/m/Y',
  property_id: 1,
  lock_expiry_minutes: 15,
  allow_same_day: true,
  max_stay: 30,
  check_in_time: '14:00',
  check_out_time: '10:00',
  enable_coupons: true,
  enable_special_requests: true,
  checkout_fields: {
    phone: { show: true, required: true },
    document: { show: true, required: false },
  },
  custom_fields: [],
  terms_conditions_type: 'text',
  terms_conditions: '',
  whatsapp_number: '',
  whatsapp_message: '',
  property_email: '',
};

// A module-level in-memory cache so the same config is shared across components
// in a single page lifecycle without hitting sessionStorage on each render.
let memCache: ArtechiaConfig | null = null;
let memCacheTs = 0;

export function useConfig(): {
  config: ArtechiaConfig;
  loading: boolean;
  error: string | null;
} {
  const [config, setConfig] = useState<ArtechiaConfig>(() => {
    // Attempt to hydrate from sessionStorage immediately (avoids flash)
    return readCache() ?? DEFAULT_CONFIG;
  });
  const [loading, setLoading] = useState<boolean>(() => readCache() === null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    // Check in-memory cache first (fastest)
    if (memCache && Date.now() - memCacheTs < CACHE_TTL_MS) {
      setConfig(memCache);
      setLoading(false);
      return;
    }

    // Check sessionStorage
    const cached = readCache();
    if (cached) {
      setConfig(cached);
      setLoading(false);
      memCache = cached;
      memCacheTs = Date.now();
      return;
    }

    // Fetch from API
    let cancelled = false;
    setLoading(true);

    getConfig()
      .then((data) => {
        if (cancelled) return;
        setConfig(data);
        setError(null);
        writeCache(data);
        memCache = data;
        memCacheTs = Date.now();
      })
      .catch((err) => {
        if (cancelled) return;
        console.warn('[useConfig] Failed to fetch config, using defaults:', err?.message);
        setError(err?.message ?? 'Config unavailable');
        // Keep whatever we had (default or stale)
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, []);

  return { config, loading, error };
}
