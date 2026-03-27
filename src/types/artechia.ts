export interface Quote {
  subtotal: number;
  subtotal_base: number;
  original_subtotal: number;
  discount_amount: number;
  promo_description: string | null;
  extras_total: number;
  taxes_total: number;
  tax_pct: number;
  total: number;
  deposit_pct: number;
  deposit_due: number;
  currency: string;
  nights: { date: string; base: number; total: number }[];
  nights_count: number;
  adults: number;
  children: number;
}

export interface RoomType {
  room_type_id: number;
  room_type_name: string;
  description: string;
  max_adults: number;
  max_children: number;
  max_occupancy: number;
  base_occupancy: number;
  bed_config: string;
  amenities_json: string;
  photos: string[];
  bookable: boolean;
  available: number;
  rate_plan_id: number;
  quote: Quote;
}

export interface AvailabilityResponse {
  room_types?: RoomType[];
  error?: string;
  message?: string;
  fail_reasons?: string[];
}

export interface CheckoutStartPayload {
  property_id: number;
  room_type_id: number;
  rate_plan_id: number;
  check_in: string;
  check_out: string;
  adults: number;
  children: number;
}

export interface CheckoutStartResponse {
  checkout_token: string;
  lock_expires: string;
  quote: Quote;
}

export interface GuestData {
  first_name: string;
  last_name: string;
  email: string;
  phone: string;
  document_type?: string;
  document_number?: string;
  country?: string;
  city?: string;
}

export interface CheckoutConfirmPayload {
  checkout_token: string;
  guest_data: GuestData;
  payment_method: string;
  coupon_code?: string;
  accept_terms: boolean;
  extras?: { id: number; quantity: number }[];
  special_requests?: string;
}

export interface BookingConfirmation {
  booking_id: number;
  booking_code: string;
  access_token: string;
  grand_total: number;
  manage_url: string;
  payment_url?: string;
}

export interface CouponPayload {
  property_id: number;
  room_type_id: number;
  rate_plan_id: number;
  check_in: string;
  check_out: string;
  adults: number;
  children: number;
  coupon_code: string;
  guest_email?: string;
}

export interface CouponResponse {
  totals?: Quote;
  coupon?: any;
  validation?: any;
  error?: string;
  message?: string;
}

export interface BookingRoom {
  room_type_id: number;
  room_type: string;
  room_unit: string | null;
  subtotal: number;
  adults: number;
  children: number;
}

export interface BookingExtra {
  name: string;
  quantity: number;
  total: number;
}

export interface BookingDetail {
  id: number;
  booking_code: string;
  status: string;
  payment_status: string;
  property: string;
  check_in: string;
  check_out: string;
  nights: number;
  adults: number;
  children: number;
  grand_total: number;
  amount_paid: number;
  balance_due: number;
  deposit_pct: number;
  deposit_due: number;
  subtotal: number;
  extras_total: number;
  taxes_total: number;
  discount_total: number;
  promo_description: string | null;
  coupon_code: string;
  currency: string;
  created_at: string;
  payment_method: string;
  bank_data: {
    bank: string;
    holder: string;
    cbu: string;
    alias: string;
    cuit: string;
  } | null;
  guest: {
    first_name: string;
    last_name: string;
    email: string;
    phone: string;
  };
  rooms: BookingRoom[];
  extras: BookingExtra[];
  special_requests: string;
  cancellation_policy: {
    is_refundable: boolean;
    deadline_days: number;
    penalty_type: string;
  };
  whatsapp_url: string;
}
