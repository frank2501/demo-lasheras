// Auto-generated types for the /public/config endpoint response.

export interface PaymentMethod {
  id: string;               // 'mercadopago' | 'bank_transfer'
  label: string;
  description: string;
  icon: string;
  display_mode?: string;    // 'details' | 'whatsapp' (bank_transfer only)
  bank_data?: {
    bank: string;
    holder: string;
    cbu: string;
    alias: string;
    cuit: string;
  };
}

export interface CheckoutFieldConfig {
  show: boolean;
  required: boolean;
}

export interface CustomCheckoutField {
  label: string;
  type: 'text' | 'textarea' | 'select' | 'checkbox';
  options?: string;   // comma-separated options for 'select' type
  required: boolean;
}

export interface ArtechiaConfig {
  // Payment
  payment_methods: PaymentMethod[];

  // Currency & Format
  currency: string;
  currency_symbol: string;
  currency_position: 'before' | 'after';
  decimal_separator: string;
  thousand_separator: string;
  decimals: number;
  date_format: string;

  // Booking rules
  property_id: number;
  lock_expiry_minutes: number;
  allow_same_day: boolean;
  max_stay: number;

  // Times
  check_in_time: string;
  check_out_time: string;

  // Checkout features
  enable_coupons: boolean;
  enable_special_requests: boolean;
  checkout_fields: {
    phone: CheckoutFieldConfig;
    document: CheckoutFieldConfig;
  };
  custom_fields: CustomCheckoutField[];
  terms_conditions_type: 'text' | 'html';
  terms_conditions: string;

  // Contact
  whatsapp_number: string;
  whatsapp_message: string;
  property_email: string;
}
