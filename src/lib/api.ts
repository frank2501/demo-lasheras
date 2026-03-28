import type {
  AvailabilityResponse,
  CheckoutStartPayload,
  CheckoutStartResponse,
  CheckoutConfirmPayload,
  BookingConfirmation,
  CouponPayload,
  CouponResponse,
  BookingDetail,
} from '@/types/artechia';
import type { ArtechiaConfig } from '@/types/config';


const BASE_URL = (process.env.NEXT_PUBLIC_WP_BASE_URL || '').replace(/\/+$/, '');

async function apiFetch<T>(endpoint: string, body: unknown): Promise<T> {
  const res = await fetch(`${BASE_URL}/wp-json/artechia/v1/public${endpoint}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  });

  if (!res.ok) {
    const errorData = await res.json().catch(() => ({}));
    throw new Error(errorData.error || `Error ${res.status}`);
  }

  return res.json();
}

const PROPERTY_ID = parseInt(process.env.NEXT_PUBLIC_PROPERTY_ID || '1', 10);

export async function searchAvailability(
  check_in: string,
  check_out: string,
  adults: number,
  children: number
): Promise<AvailabilityResponse> {
  return apiFetch<AvailabilityResponse>('/availability', {
    property_id: PROPERTY_ID,
    check_in,
    check_out,
    adults,
    children,
  });
}

export async function startCheckout(
  payload: CheckoutStartPayload
): Promise<CheckoutStartResponse> {
  return apiFetch<CheckoutStartResponse>('/checkout/start', payload);
}

export async function confirmCheckout(
  payload: CheckoutConfirmPayload
): Promise<BookingConfirmation> {
  return apiFetch<BookingConfirmation>('/checkout/confirm', payload);
}

export async function applyCoupon(
  payload: CouponPayload
): Promise<CouponResponse> {
  return apiFetch<CouponResponse>('/quote', payload);
}

export async function checkLockStatus(
  checkout_token: string
): Promise<{ active: boolean; reason?: string }> {
  return apiFetch<{ active: boolean; reason?: string }>('/checkout/lock-status', { checkout_token });
}

export interface CalendarHintDay {
  s: 'full' | 'low' | 'available';
  p?: number; // promo percentage
}

export interface CalendarHintsResponse {
  days: Record<string, CalendarHintDay>;
  total_units: number;
}

export async function getCalendarHints(months = 3): Promise<CalendarHintsResponse> {
  const res = await fetch(
    `${BASE_URL}/wp-json/artechia/v1/public/calendar-hints`,
    {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ property_id: PROPERTY_ID, months }),
      next: { revalidate: 30 }
    }
  );

  if (!res.ok) {
    return { days: {}, total_units: 0 };
  }

  return res.json();
}

/**
 * Fetch dynamic plugin configuration.
 * Uses GET so it can benefit from CDN/ISR caching on the Next.js side.
 * On the client, results are cached in sessionStorage for 5 minutes.
 */
export async function getConfig(): Promise<ArtechiaConfig> {
  const res = await fetch(
    `${BASE_URL}/wp-json/artechia/v1/public/config`,
    { next: { revalidate: 60 } } // ISR: revalidate every 60 seconds on the server
  );

  if (!res.ok) {
    throw new Error(`Config fetch failed: ${res.status}`);
  }

  return res.json();
}

/**
 * Fetch booking details for the "Mi Reserva" portal.
 * Uses POST with token in the body to avoid WAF blocking query params.
 */
export async function getBooking(code: string, token: string): Promise<BookingDetail> {
  const res = await fetch(
    `${BASE_URL}/wp-json/artechia/v1/public/booking/${encodeURIComponent(code)}`,
    {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ token }),
    }
  );

  const data = await res.json();

  if (!res.ok || data.error) {
    throw new Error(data.error || `Error ${res.status}`);
  }

  return data;
}

/**
 * Find a booking by code + guest email.
 * Returns { booking_code, access_token } on success.
 */
export async function findBooking(code: string, email: string): Promise<{ booking_code: string; access_token: string }> {
  const res = await fetch(
    `${BASE_URL}/wp-json/artechia/v1/public/booking/find`,
    {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ code, email }),
    }
  );

  const data = await res.json();

  if (!res.ok || data.success === false) {
    throw new Error(data.message || `Error ${res.status}`);
  }

  return data;
}
