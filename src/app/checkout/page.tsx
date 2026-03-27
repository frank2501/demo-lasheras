'use client';

import { useState, useEffect, useRef, FormEvent } from 'react';
import { useRouter } from 'next/navigation';
import {
  ArrowLeft,
  User,
  Mail,
  Phone,
  FileText,
  MapPin,
  Globe,
  MessageSquare,
  Tag,
  CreditCard,
  CheckCircle,
  AlertCircle,
  Clock,
  Loader2,
  Copy,
} from 'lucide-react';
import { startCheckout, confirmCheckout, applyCoupon, checkLockStatus } from '@/lib/api';
import type { Quote, BookingConfirmation } from '@/types/artechia';
import BookingStepper from '@/components/ui/BookingStepper';
import { useConfig } from '@/lib/useConfig';

type Step = 'form' | 'confirming' | 'success' | 'error';

function formatShortDate(iso: string) {
  if (!iso) return '—';
  const [y, m, d] = iso.split('-');
  return `${d}/${m}/${y.slice(-2)}`;
}

function CheckoutContent() {
  const router = useRouter();

  // Read booking data from sessionStorage (set by disponibilidad page)
  const [bookingData] = useState(() => {
    try {
      const raw = sessionStorage.getItem('checkout_data');
      return raw ? JSON.parse(raw) : null;
    } catch {
      return null;
    }
  });

  const roomTypeId = Number(bookingData?.room_type_id || 0);
  const ratePlanId = Number(bookingData?.rate_plan_id || 0);
  const checkIn = bookingData?.check_in || '';
  const checkOut = bookingData?.check_out || '';
  const adults = Number(bookingData?.adults || 2);
  const childrenCount = Number(bookingData?.children || 0);
  const roomName = bookingData?.room_name || 'Habitación';
  const totalPrice = Number(bookingData?.total || 0);
  const currency = bookingData?.currency || 'ARS';
  const nightsCount = Number(bookingData?.nights || 1);

  const [step, setStep] = useState<Step>('form');
  const [token, setToken] = useState('');
  const [lockLoading, setLockLoading] = useState(true);
  const [lockError, setLockError] = useState('');
  const [lockTime, setLockTime] = useState<Date | null>(null);
  const [timeLeftStr, setTimeLeftStr] = useState<string>('');

  // Guest form state
  const [firstName, setFirstName] = useState('');
  const [lastName, setLastName] = useState('');
  const [email, setEmail] = useState('');
  const [phone, setPhone] = useState('');
  const [docType, setDocType] = useState('DNI');
  const [docNumber, setDocNumber] = useState('');
  const [specialRequests, setSpecialRequests] = useState('');
  const [showTerms, setShowTerms] = useState(false);
  const [couponCode, setCouponCode] = useState('');
  const [couponApplied, setCouponApplied] = useState(false);
  const [couponError, setCouponError] = useState('');
  const [couponLoading, setCouponLoading] = useState(false);
  const { config } = useConfig();
  const lockExpiryMs = (config.lock_expiry_minutes ?? 15) * 60 * 1000;

  // Default payment method: first available from plugin config
  const [paymentMethod, setPaymentMethod] = useState(() => config.payment_methods[0]?.id ?? 'mercadopago');
  const [acceptTerms, setAcceptTerms] = useState(false);
  const [currentQuote, setCurrentQuote] = useState<Quote | null>(null);

  const [confirmError, setConfirmError] = useState('');
  const [confirmation, setConfirmation] = useState<BookingConfirmation | null>(null);
  const lockingRef = useRef(false);

  // Lock the room on mount — reuse existing token on refresh
  useEffect(() => {
    if (!roomTypeId || !checkIn || !checkOut) {
      setLockError('Faltan datos de la reserva. Volvé a buscar disponibilidad.');
      setLockLoading(false);
      return;
    }

    // Build a unique key for this exact booking combination
    const storageKey = `checkout_${roomTypeId}_${ratePlanId}_${checkIn}_${checkOut}_${adults}_${childrenCount}`;

    const lockRoom = async () => {
      // Prevent concurrent calls (e.g. double mount)
      if (lockingRef.current) return;
      lockingRef.current = true;

      // Check if we already have a token for this exact booking
      try {
        const cached = sessionStorage.getItem(storageKey);
        if (cached) {
          const { checkout_token, locked_at } = JSON.parse(cached);
          const lockedDate = new Date(locked_at);
          const elapsed = Date.now() - lockedDate.getTime();
          // Reuse only if the lock hasn't expired (15 min)
          if (elapsed < lockExpiryMs) {
            setToken(checkout_token);
            setLockTime(lockedDate);
            setLockLoading(false);
            return;
          }
          // Expired — remove stale entry
          sessionStorage.removeItem(storageKey);
        }
      } catch {
        // sessionStorage parse error — continue to create new checkout
      }

      try {
        const res = await startCheckout({
          property_id: parseInt(process.env.NEXT_PUBLIC_PROPERTY_ID || '1', 10),
          room_type_id: roomTypeId,
          check_in: checkIn,
          check_out: checkOut,
          adults,
          children: childrenCount,
          rate_plan_id: ratePlanId,
        });
        setToken(res.checkout_token);
        const now = new Date();
        setLockTime(now);
        // Persist to sessionStorage so refreshes reuse this token
        sessionStorage.setItem(storageKey, JSON.stringify({
          checkout_token: res.checkout_token,
          locked_at: now.toISOString(),
        }));
      } catch (err) {
        setLockError('No pudimos reservar la habitación. Es posible que ya no esté disponible.');
      } finally {
        setLockLoading(false);
      }
    };

    lockRoom();
  }, [roomTypeId, checkIn, checkOut, adults, childrenCount, ratePlanId]);

  // Poll lock status every 30s to detect admin cancellation
  useEffect(() => {
    if (!token || step !== 'form') return;

    const interval = setInterval(async () => {
      try {
        const res = await checkLockStatus(token);
        if (!res.active) {
          clearInterval(interval);
          const storageKey = `checkout_${roomTypeId}_${ratePlanId}_${checkIn}_${checkOut}_${adults}_${childrenCount}`;
          sessionStorage.removeItem(storageKey);

          // If the local 15-min timer hasn't elapsed yet, the admin cancelled it.
          // If it already elapsed, it's a natural expiry.
          if (isLockExpired()) {
            setConfirmError('Tu reserva expiró. Han pasado más de 15 minutos. Volvé a buscar disponibilidad.');
            setStep('error');
          } else {
            setLockError(
              'Tu reserva fue cancelada por el hotel. Por favor, volvé a buscar disponibilidad.'
            );
          }
        }
      } catch {
        // Network error — ignore, will retry on next interval
      }
    }, 30_000);

    return () => clearInterval(interval);
  }, [token, step, roomTypeId, ratePlanId, checkIn, checkOut, adults, childrenCount]);

  // Check lock expiry (from plugin config, defaults to 15 min)
  const isLockExpired = () => {
    if (!lockTime) return false;
    return Date.now() - lockTime.getTime() > lockExpiryMs;
  };

  // Update visual timer every second
  useEffect(() => {
    if (!lockTime || step !== 'form') return;
    
    const updateTimer = () => {
      const elapsed = Date.now() - lockTime.getTime();
      const remaining = Math.max(0, lockExpiryMs - elapsed);
      
      if (remaining === 0 && !isLockExpired()) {
        // Will be caught by confirmation/polling
      }
      
      const mins = Math.floor(remaining / 60000);
      const secs = Math.floor((remaining % 60000) / 1000);
      setTimeLeftStr(`${mins}:${secs.toString().padStart(2, '0')}`);
    };

    updateTimer(); // Initial call
    const timerInterval = setInterval(updateTimer, 1000);
    return () => clearInterval(timerInterval);
  }, [lockTime, lockExpiryMs, step]);

  const handleApplyCoupon = async () => {
    if (!couponCode.trim() || !token) return;
    setCouponLoading(true);
    setCouponError('');
    try {
      const res = await applyCoupon({
        property_id: parseInt(process.env.NEXT_PUBLIC_PROPERTY_ID || '1', 10),
        room_type_id: roomTypeId,
        rate_plan_id: ratePlanId,
        check_in: checkIn,
        check_out: checkOut,
        adults: adults,
        children: childrenCount,
        coupon_code: couponCode.trim(),
        guest_email: email,
      });

      if (res.totals && !res.validation?.coupon_error) {
        setCouponApplied(true);
        setCurrentQuote(res.totals);
      } else {
        const errMap: Record<string, string> = {
          'INVALID_CODE': 'Código inválido.',
          'EXPIRED': 'El cupón ha expirado.',
          'NOT_STARTED': 'El cupón aún no es válido.',
          'MIN_NIGHTS_NOT_MET': 'No cumple con el mínimo de noches.',
          'LIMIT_REACHED': 'Cupón agotado.',
          'ROOM_NOT_ELIGIBLE': 'No aplica para esta habitación.',
          'RATE_NOT_ELIGIBLE': 'No aplica para esta tarifa.',
        };
        setCouponError(errMap[res.validation?.coupon_error || ''] || res.error || 'El cupón no es válido.');
      }
    } catch {
      setCouponError('No se pudo aplicar el cupón. Intentá de nuevo.');
    } finally {
      setCouponLoading(false);
    }
  };

  const handleConfirm = async (e: FormEvent) => {
    e.preventDefault();
    setConfirmError('');

    if (isLockExpired()) {
      setStep('error');
      setConfirmError('Tu reserva expiró. Han pasado más de 15 minutos. Volvé a buscar disponibilidad.');
      return;
    }

    if (!acceptTerms) {
      setConfirmError('Debés aceptar los términos y condiciones.');
      return;
    }

    setStep('confirming');

    try {
      // Verify the lock is still active before confirming
      const lockCheck = await checkLockStatus(token);
      if (!lockCheck.active) {
        const storageKey = `checkout_${roomTypeId}_${ratePlanId}_${checkIn}_${checkOut}_${adults}_${childrenCount}`;
        sessionStorage.removeItem(storageKey);
        setLockError(
          'Tu reserva fue cancelada por el hotel. Por favor, volvé a buscar disponibilidad.'
        );
        return;
      }

      const res = await confirmCheckout({
        checkout_token: token,
        guest_data: {
          first_name: firstName,
          last_name: lastName,
          email,
          phone,
          document_type: docType,
          document_number: docNumber,
        },
        payment_method: paymentMethod,
        coupon_code: couponApplied ? couponCode : undefined,
        accept_terms: true,
        special_requests: specialRequests,
      });

      // Clear cached checkout token after successful confirmation
      const storageKey = `checkout_${roomTypeId}_${ratePlanId}_${checkIn}_${checkOut}_${adults}_${childrenCount}`;
      sessionStorage.removeItem(storageKey);

      // If MercadoPago, redirect
      if (res.payment_url) {
        window.location.href = res.payment_url;
        return;
      }

      setConfirmation(res);
      setStep('success');
    } catch (err: any) {
      const msg = err?.message || '';
      console.error('[Checkout Confirm Error]', msg, err);
      if (msg.includes('LOCK') || msg.includes('EXPIRED') || msg.includes('INVALID_TOKEN')) {
        const storageKey = `checkout_${roomTypeId}_${ratePlanId}_${checkIn}_${checkOut}_${adults}_${childrenCount}`;
        sessionStorage.removeItem(storageKey);
        // Distinguish: if local timer already elapsed → expired; otherwise → server cancelled
        if (isLockExpired()) {
          setConfirmError('Tu reserva expiró. Han pasado más de 15 minutos. Volvé a buscar disponibilidad.');
          setStep('error');
        } else {
          setLockError('Tu reserva fue cancelada por el hotel. Por favor, volvé a buscar disponibilidad.');
        }
      } else {
        setConfirmError(`Error al confirmar: ${msg || 'Error desconocido'}. Intentá de nuevo.`);
        setStep('form');
      }
    }
  };

  const displayTotal = currentQuote ? currentQuote.total : totalPrice;

  const formatARS = (n: number) =>
    `$ ${n.toLocaleString('es-AR', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}`;

  // --- Loading lock ---
  if (lockLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-blanco-roto pt-32">
        <div className="text-center">
          <Loader2 size={40} className="animate-spin text-azul-cielo mx-auto mb-4" />
          <p className="text-azul-marino font-semibold">Reservando habitación...</p>
          <p className="text-sm text-foreground/50 mt-1">Estamos bloqueando tu habitación por 15 minutos.</p>
        </div>
      </div>
    );
  }

  // --- Lock error ---
  if (lockError) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-blanco-roto pt-32 px-4">
        <div className="card p-8 max-w-md text-center">
          <AlertCircle size={40} className="text-red-500 mx-auto mb-4" />
          <h2
            className="text-xl font-bold text-azul-marino mb-3"
            style={{ fontFamily: 'var(--font-display)' }}
          >
            No pudimos continuar
          </h2>
          <p className="text-sm text-foreground/70 mb-6">{lockError}</p>
          <button onClick={() => router.push('/#habitaciones')} className="btn-primary">
            Volver a buscar
          </button>
        </div>
      </div>
    );
  }

  // --- Success ---
  if (step === 'success' && confirmation) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-blanco-roto pt-32 px-4">
        <div className="card p-8 max-w-lg text-center">
          <div className="w-16 h-16 mx-auto mb-4 rounded-full bg-green-100 flex items-center justify-center">
            <CheckCircle size={32} className="text-green-600" />
          </div>
          <h2
            className="text-2xl font-bold text-azul-marino mb-2"
            style={{ fontFamily: 'var(--font-display)' }}
          >
            ¡Reserva confirmada!
          </h2>
          <p className="text-sm text-foreground/60 mb-6">
            Tu código de reserva es:
          </p>
          <div className="bg-crema rounded-lg p-4 mb-6 flex items-center justify-center gap-3">
            <span className="text-2xl font-bold text-azul-marino tracking-wider">
              {confirmation.booking_code}
            </span>
            <button
              onClick={() => navigator.clipboard.writeText(confirmation.booking_code)}
              className="text-azul-cielo hover:text-azul-marino transition-colors"
              title="Copiar código"
            >
              <Copy size={18} />
            </button>
          </div>
          <p className="text-sm text-foreground/70 mb-8">
            Recibirás las instrucciones de pago y los detalles de tu estadía por email.
          </p>
          <div className="flex flex-col sm:flex-row gap-3 justify-center">
            <button
              onClick={() => router.push(`/mi-reserva?code=${confirmation.booking_code}&token=${confirmation.access_token}`)}
              className="btn-primary"
            >
              Ver mi reserva
            </button>
            <button onClick={() => router.push('/')} className="btn-secondary">
              Volver al inicio
            </button>
          </div>
        </div>
      </div>
    );
  }

  // --- Error (lock expired) ---
  if (step === 'error') {
    return (
      <div className="min-h-screen flex items-center justify-center bg-blanco-roto pt-32 px-4">
        <div className="card p-8 max-w-md text-center">
          <Clock size={40} className="text-dorado mx-auto mb-4" />
          <h2
            className="text-xl font-bold text-azul-marino mb-3"
            style={{ fontFamily: 'var(--font-display)' }}
          >
            Reserva expirada
          </h2>
          <p className="text-sm text-foreground/70 mb-6">{confirmError}</p>
          <button onClick={() => router.push('/#habitaciones')} className="btn-primary">
            Volver a buscar disponibilidad
          </button>
        </div>
      </div>
    );
  }

  // --- Form ---
  return (
    <div className="min-h-screen bg-blanco-roto pt-32 pb-16 px-4">
      <div className="max-w-5xl mx-auto">
        {/* Stepper */}
        <BookingStepper current={3} />

        {/* Back button */}
        <button
          onClick={() => router.push('/#habitaciones')}
          className="flex items-center gap-2 text-sm text-azul-cielo hover:text-azul-marino mb-6 transition-colors"
        >
          <ArrowLeft size={16} />
          Volver a habitaciones
        </button>

        <h1
          className="text-3xl font-bold text-azul-marino mb-8"
          style={{ fontFamily: 'var(--font-display)' }}
        >
          Completá tu reserva
        </h1>

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
          {/* Guest form */}
          <form onSubmit={handleConfirm} className="lg:col-span-2 space-y-6">
            {/* Personal data */}
            <div className="card p-6">
              <h3 className="font-bold text-azul-marino mb-4 flex items-center gap-2">
                <User size={18} className="text-azul-cielo" />
                Datos del huésped
              </h3>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="form-label" htmlFor="first-name">Nombre *</label>
                  <input id="first-name" type="text" className="form-input" value={firstName} onChange={(e) => setFirstName(e.target.value)} required />
                </div>
                <div>
                  <label className="form-label" htmlFor="last-name">Apellido *</label>
                  <input id="last-name" type="text" className="form-input" value={lastName} onChange={(e) => setLastName(e.target.value)} required />
                </div>
                <div>
                  <label className="form-label" htmlFor="checkout-email">
                    <Mail size={13} className="inline mr-1 -mt-0.5" />
                    Email *
                  </label>
                  <input id="checkout-email" type="email" className="form-input" value={email} onChange={(e) => setEmail(e.target.value)} required />
                </div>
                <div>
                  <label className="form-label" htmlFor="checkout-phone">
                    <Phone size={13} className="inline mr-1 -mt-0.5" />
                    Teléfono *
                  </label>
                  <input id="checkout-phone" type="tel" className="form-input" value={phone} onChange={(e) => setPhone(e.target.value)} required />
                </div>
              </div>
            </div>

            {/* Document */}
            <div className="card p-6">
              <h3 className="font-bold text-azul-marino mb-4 flex items-center gap-2">
                <FileText size={18} className="text-azul-cielo" />
                Documento
              </h3>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="form-label" htmlFor="doc-type">Tipo de documento</label>
                  <select id="doc-type" className="form-input" value={docType} onChange={(e) => setDocType(e.target.value)}>
                    <option value="DNI">DNI</option>
                    <option value="Pasaporte">Pasaporte</option>
                    <option value="CUIT/CUIL">CUIT/CUIL</option>
                    <option value="Otro">Otro</option>
                  </select>
                </div>
                <div>
                  <label className="form-label" htmlFor="doc-number">Número de documento *</label>
                  <input id="doc-number" type="text" className="form-input" value={docNumber} onChange={(e) => setDocNumber(e.target.value)} required />
                </div>
              </div>
            </div>

            {/* Special requests — shown only if enabled in plugin config */}
            {config.enable_special_requests && (
              <div className="card p-6">
                <h3 className="font-bold text-azul-marino mb-4 flex items-center gap-2">
                  <MessageSquare size={18} className="text-azul-cielo" />
                  Pedidos especiales
                </h3>
                <textarea
                  className="form-input min-h-[80px] resize-y"
                  value={specialRequests}
                  onChange={(e) => setSpecialRequests(e.target.value)}
                  placeholder="¿Tenés algún pedido especial? (opcional)"
                />
              </div>
            )}

            {/* Coupon — shown only if enabled in plugin config */}
            {config.enable_coupons && (
              <div className="card p-6">
                <h3 className="font-bold text-azul-marino mb-4 flex items-center gap-2">
                  <Tag size={18} className="text-azul-cielo" />
                  Cupón de descuento
                </h3>
                <div className="flex gap-3">
                  <input
                    type="text"
                    className="form-input flex-1"
                    value={couponCode}
                    onChange={(e) => { setCouponCode(e.target.value); setCouponApplied(false); setCouponError(''); }}
                    placeholder="Código de cupón"
                    disabled={couponApplied}
                  />
                  <button
                    type="button"
                    onClick={handleApplyCoupon}
                    className="btn-secondary whitespace-nowrap"
                    disabled={couponLoading || couponApplied || !couponCode.trim()}
                  >
                    {couponLoading ? 'Aplicando...' : couponApplied ? '✓ Aplicado' : 'Aplicar'}
                  </button>
                </div>
                {couponError && (
                  <p className="text-red-600 text-sm mt-2">{couponError}</p>
                )}
                {couponApplied && (
                  <p className="text-green-700 text-sm mt-2">¡Cupón aplicado correctamente!</p>
                )}
              </div>
            )}

            {/* Payment method — dynamic from plugin config */}
            {config.payment_methods.length > 0 && (
              <div className="card p-6">
                <h3 className="font-bold text-azul-marino mb-4 flex items-center gap-2">
                  <CreditCard size={18} className="text-azul-cielo" />
                  Método de pago
                </h3>
                <div className="space-y-3">
                  {config.payment_methods.map((method) => (
                    <label
                      key={method.id}
                      className="flex items-center gap-3 cursor-pointer p-3 rounded-lg border border-transparent hover:border-azul-cielo/20 hover:bg-crema/50 transition-colors"
                    >
                      <input
                        type="radio"
                        name="payment"
                        value={method.id}
                        checked={paymentMethod === method.id}
                        onChange={(e) => setPaymentMethod(e.target.value)}
                        className="accent-azul-cielo"
                      />
                      <div>
                        <p className="font-semibold text-sm text-azul-marino">{method.label}</p>
                        <p className="text-xs text-foreground/50">{method.description}</p>
                      </div>
                    </label>
                  ))}
                </div>
              </div>
            )}

            {/* Terms */}
            <div className="card p-6">
              <label className="flex items-start gap-3 cursor-pointer">
                <input
                  type="checkbox"
                  checked={acceptTerms}
                  onChange={(e) => setAcceptTerms(e.target.checked)}
                  className="accent-azul-cielo mt-1"
                />
                <span className="text-sm text-foreground/70">
                  Acepto los{' '}
                  <button type="button" onClick={(e) => { e.preventDefault(); setShowTerms(true); }} className="text-azul-cielo hover:underline">
                    términos y condiciones
                  </button>{' '}
                  y la política de privacidad del hotel.
                </span>
              </label>
            </div>

            {/* Error */}
            {confirmError && step === 'form' && (
              <div className="flex items-center gap-2 text-red-600 text-sm bg-red-50 p-4 rounded-lg">
                <AlertCircle size={16} />
                {confirmError}
              </div>
            )}

            {/* Submit */}
            <button
              type="submit"
              className="btn-primary w-full py-4 text-base"
              disabled={step === 'confirming'}
            >
              {step === 'confirming' ? (
                <span className="flex items-center justify-center gap-2">
                  <Loader2 size={18} className="animate-spin" />
                  Confirmando reserva...
                </span>
              ) : (
                'Confirmar reserva'
              )}
            </button>
          </form>

          {/* Sidebar — reservation summary */}
          <div className="lg:col-span-1">
            <div className="card p-6 sticky top-24">
              <h3
                className="text-lg font-bold text-azul-marino mb-4"
                style={{ fontFamily: 'var(--font-display)' }}
              >
                Resumen de reserva
              </h3>

              <div className="space-y-3 text-sm">
                <div className="flex justify-between">
                  <span className="text-foreground/60">Unidad</span>
                  <span className="font-semibold text-azul-marino">{roomName}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-foreground/60">Check-in</span>
                  <span className="font-medium">{formatShortDate(checkIn)}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-foreground/60">Check-out</span>
                  <span className="font-medium">{formatShortDate(checkOut)}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-foreground/60">Noches</span>
                  <span>{nightsCount}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-foreground/60">Huéspedes</span>
                  <span>{adults} adultos{childrenCount > 0 ? `, ${childrenCount} menores` : ''}</span>
                </div>

                {currentQuote && (
                  <>
                    <hr className="border-crema my-2" />
                    <div className="flex justify-between text-xs">
                      <span className="text-foreground/50">Subtotal</span>
                      <span>{formatARS(currentQuote.subtotal)}</span>
                    </div>
                    {currentQuote.discount_amount > 0 && (
                      <div className="flex justify-between text-xs text-green-700">
                        <span>Descuento</span>
                        <span>-{formatARS(currentQuote.discount_amount)}</span>
                      </div>
                    )}
                    {currentQuote.taxes_total > 0 && (
                      <div className="flex justify-between text-xs">
                        <span className="text-foreground/50">Impuestos</span>
                        <span>{formatARS(currentQuote.taxes_total)}</span>
                      </div>
                    )}
                  </>
                )}

                <hr className="border-crema my-2" />
                <div className="flex justify-between items-end">
                  <span className="font-bold text-azul-marino pb-1">Total</span>
                  <div className="text-right">
                    <span className="text-xl font-bold text-azul-marino">{formatARS(displayTotal)}</span>
                    <span className="text-xs font-semibold text-foreground/40 ml-1.5">{currency}</span>
                  </div>
                </div>
              </div>

              {/* Timer notice */}
              <div className="mt-6 p-3 bg-crema rounded-lg flex items-start gap-2">
                <Clock size={14} className="text-dorado mt-0.5 shrink-0" />
                <div className="text-xs text-foreground/60 w-full">
                  <p className="mb-1">
                    Tu habitación está reservada por <strong className="text-azul-marino">{config.lock_expiry_minutes ?? 15} minutos</strong>.
                  </p>
                  {timeLeftStr && (
                    <div className="flex justify-between items-center bg-white/50 px-2 py-1.5 rounded mt-2">
                      <span>Tiempo restante:</span>
                      <span className="font-mono font-bold text-azul-marino text-sm">{timeLeftStr}</span>
                    </div>
                  )}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Terms Modal */}
      {showTerms && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
          <div className="bg-white rounded-xl max-w-lg w-full p-6 max-h-[85vh] flex flex-col shadow-2xl">
            <div className="flex justify-between items-center mb-4">
              <h2 className="text-xl font-bold text-azul-marino" style={{ fontFamily: 'var(--font-display)' }}>
                Términos y Condiciones
              </h2>
              <button type="button" onClick={() => setShowTerms(false)} className="text-foreground/40 hover:text-azul-marino transition-colors">
                ✕
              </button>
            </div>
            <div className="flex-1 overflow-y-auto text-sm text-foreground/70 space-y-4 pr-2">
              <h3 className="font-semibold text-azul-marino">1. Políticas de Cancelación</h3>
              <p>Las cancelaciones o modificaciones deben realizarse con la debida anticipación. En caso de cancelaciones tardías o "no show", el hotel se reserva el derecho de retener el importe de la seña abonada.</p>
              
              <h3 className="font-semibold text-azul-marino">2. Horarios</h3>
              <p>El horario de entrada (check-in) es a partir de las 14:00 horas, y el horario de salida (check-out) es hasta las 10:00 horas. Sujeto a disponibilidad, se pueden solicitar ingresos tempranos o salidas tardías (pueden aplicar cargos adicionales).</p>
              
              <h3 className="font-semibold text-azul-marino">3. Condiciones de Pago</h3>
              <p>Para confirmar su reserva, es necesario abonar la seña indicada. El saldo restante deberá ser abonado al momento del ingreso al alojamiento, utilizando los métodos de pago aceptados, o en las fechas estipuladas por la administración.</p>
              
              <h3 className="font-semibold text-azul-marino">4. Políticas de Convivencia</h3>
              <p>El alojamiento se reserva el derecho de admisión y permanencia. Solicitamos respetar las normas de convivencia, mantener niveles de ruido moderados en horarios de descanso y el buen uso de las instalaciones.</p>
            </div>
            <div className="mt-6 pt-4 border-t border-gray-100 flex justify-end">
              <button type="button" onClick={() => setShowTerms(false)} className="btn-primary w-full sm:w-auto px-8">
                Entendido
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

export default function CheckoutPage() {
  return <CheckoutContent />;
}
