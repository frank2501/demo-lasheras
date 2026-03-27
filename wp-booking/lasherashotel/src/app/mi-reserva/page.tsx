'use client';

import { useState, useEffect, Suspense } from 'react';
import { useSearchParams } from 'next/navigation';
import {
  CheckCircle,
  Clock,
  XCircle,
  AlertCircle,
  CalendarDays,
  Users,
  Bed,
  CreditCard,
  Copy,
  Loader2,
  PhoneCall,
} from 'lucide-react';
import { getBooking } from '@/lib/api';
import type { BookingDetail } from '@/types/artechia';

// ── Status helpers ────────────────────────────────────────────────────────────

const STATUS_MAP: Record<string, { label: string; color: string; Icon: React.ElementType }> = {
  pending:      { label: 'Pendiente de pago',    color: 'text-amber-600',  Icon: Clock },
  hold:         { label: 'En proceso de pago',   color: 'text-blue-500',   Icon: Clock },
  confirmed:    { label: 'Confirmada',            color: 'text-green-600',  Icon: CheckCircle },
  deposit_paid: { label: 'Depósito pagado',       color: 'text-green-500',  Icon: CheckCircle },
  paid:         { label: 'Pagada en su totalidad',color: 'text-green-700',  Icon: CheckCircle },
  checked_in:   { label: 'En estadía',            color: 'text-indigo-600', Icon: CheckCircle },
  checked_out:  { label: 'Check-out realizado',   color: 'text-gray-500',   Icon: CheckCircle },
  cancelled:    { label: 'Cancelada',             color: 'text-red-600',    Icon: XCircle },
  no_show:      { label: 'No presentado',         color: 'text-red-400',    Icon: XCircle },
};

function formatDate(d: string) {
  const [y, m, day] = d.split('-');
  return `${day}/${m}/${y}`;
}

function formatARS(n: number) {
  return `$ ${n.toLocaleString('es-AR', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}`;
}

// ── Main Content ──────────────────────────────────────────────────────────────

function MiReservaContent() {
  const params = useSearchParams();
  const code  = params.get('code') ?? '';
  const token = params.get('token') ?? '';

  const [booking, setBooking] = useState<BookingDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError]     = useState('');
  const [copied, setCopied]   = useState(false);

  useEffect(() => {
    if (!code || !token) {
      setError('Enlace de reserva inválido. Verificá el email que recibiste.');
      setLoading(false);
      return;
    }

    getBooking(code, token)
      .then(setBooking)
      .catch((err) => setError(err?.message === 'NOT_FOUND'
        ? 'No encontramos tu reserva. Verificá el código.'
        : err?.message === 'INVALID_TOKEN'
          ? 'Token inválido. Usá el enlace del email original.'
          : 'No pudimos cargar tu reserva. Intentá de nuevo.'
      ))
      .finally(() => setLoading(false));
  }, [code, token]);

  const copyCode = () => {
    navigator.clipboard.writeText(code);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  // ── Loading ────────────────────────────────────────────────────────────────
  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-blanco-roto pt-32">
        <div className="text-center">
          <Loader2 size={40} className="animate-spin text-azul-cielo mx-auto mb-4" />
          <p className="text-azul-marino font-semibold">Cargando tu reserva...</p>
        </div>
      </div>
    );
  }

  // ── Error ──────────────────────────────────────────────────────────────────
  if (error || !booking) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-blanco-roto pt-32 px-4">
        <div className="card p-8 max-w-md text-center">
          <AlertCircle size={40} className="text-red-500 mx-auto mb-4" />
          <h1 className="text-xl font-bold text-azul-marino mb-3" style={{ fontFamily: 'var(--font-display)' }}>
            No pudimos encontrar tu reserva
          </h1>
          <p className="text-sm text-foreground/70 mb-6">{error}</p>
          <a href="/" className="btn-primary">Volver al inicio</a>
        </div>
      </div>
    );
  }

  const statusInfo = STATUS_MAP[booking.status] ?? { label: booking.status, color: 'text-gray-500', Icon: AlertCircle };
  const StatusIcon = statusInfo.Icon;
  const isCancelled = booking.status === 'cancelled';

  return (
    <div className="min-h-screen bg-blanco-roto pt-32 pb-16 px-4">
      <div className="max-w-3xl mx-auto">

        {/* Header */}
        <div className="mb-8">
          <h1 className="text-3xl font-bold text-azul-marino mb-1" style={{ fontFamily: 'var(--font-display)' }}>
            Mi Reserva
          </h1>
          <div className="flex items-center gap-3 flex-wrap">
            {/* Booking code */}
            <div className="flex items-center gap-2 bg-white border border-crema rounded-lg px-3 py-1.5">
              <span className="font-mono font-bold text-azul-marino tracking-wider">{booking.booking_code}</span>
              <button onClick={copyCode} className="text-azul-cielo hover:text-azul-marino transition-colors" title="Copiar código">
                <Copy size={15} />
              </button>
              {copied && <span className="text-xs text-green-600">¡Copiado!</span>}
            </div>
            {/* Status badge */}
            <span className={`flex items-center gap-1.5 text-sm font-semibold ${statusInfo.color}`}>
              <StatusIcon size={16} />
              {statusInfo.label}
            </span>
          </div>
        </div>

        <div className="space-y-6">
          {/* Cancelled notice */}
          {isCancelled && (
            <div className="bg-red-50 border border-red-200 rounded-lg p-4 flex items-start gap-3">
              <XCircle size={18} className="text-red-500 mt-0.5 shrink-0" />
              <p className="text-sm text-red-700">Esta reserva fue cancelada y ya no tiene validez.</p>
            </div>
          )}

          {/* Dates & Guests */}
          <div className="card p-6">
            <h2 className="font-bold text-azul-marino mb-4 flex items-center gap-2">
              <CalendarDays size={18} className="text-azul-cielo" />
              Detalles de la estadía
            </h2>
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
              <div>
                <p className="text-foreground/50 text-xs mb-0.5">Check-in</p>
                <p className="font-semibold text-azul-marino">{formatDate(booking.check_in)}</p>
              </div>
              <div>
                <p className="text-foreground/50 text-xs mb-0.5">Check-out</p>
                <p className="font-semibold text-azul-marino">{formatDate(booking.check_out)}</p>
              </div>
              <div>
                <p className="text-foreground/50 text-xs mb-0.5">Noches</p>
                <p className="font-semibold text-azul-marino">{booking.nights}</p>
              </div>
              <div>
                <p className="text-foreground/50 text-xs mb-0.5">Huéspedes</p>
                <p className="font-semibold text-azul-marino flex items-center gap-1">
                  <Users size={13} />
                  {booking.adults}{booking.children > 0 ? ` + ${booking.children} menor${booking.children > 1 ? 'es' : ''}` : ''}
                </p>
              </div>
            </div>

            {/* Rooms */}
            {booking.rooms.length > 0 && (
              <div className="mt-4 pt-4 border-t border-crema">
                {booking.rooms.map((r, i) => (
                  <div key={i} className="flex items-center gap-2 text-sm text-foreground/70">
                    <Bed size={14} className="text-azul-cielo shrink-0" />
                    <span>{r.room_type}{r.room_unit ? ` — ${r.room_unit}` : ''}</span>
                  </div>
                ))}
              </div>
            )}
          </div>

          {/* Payment summary */}
          <div className="card p-6">
            <h2 className="font-bold text-azul-marino mb-4 flex items-center gap-2">
              <CreditCard size={18} className="text-azul-cielo" />
              Resumen de pago
            </h2>
            <div className="space-y-2 text-sm">
              {booking.subtotal > 0 && (
                <div className="flex justify-between">
                  <span className="text-foreground/60">Subtotal</span>
                  <span>{formatARS(booking.subtotal)}</span>
                </div>
              )}
              {booking.extras.map((e, i) => (
                <div key={i} className="flex justify-between text-xs">
                  <span className="text-foreground/50">{e.name} ×{e.quantity}</span>
                  <span>{formatARS(e.total)}</span>
                </div>
              ))}
              {booking.taxes_total > 0 && (
                <div className="flex justify-between text-xs">
                  <span className="text-foreground/50">Impuestos</span>
                  <span>{formatARS(booking.taxes_total)}</span>
                </div>
              )}
              {booking.discount_total > 0 && (
                <div className="flex justify-between text-xs text-green-700">
                  <span>Descuento</span>
                  <span>-{formatARS(booking.discount_total)}</span>
                </div>
              )}
              <div className="flex justify-between font-bold text-azul-marino border-t border-crema pt-2 mt-2">
                <span>Total</span>
                <span className="text-lg">{formatARS(booking.grand_total)}</span>
              </div>
              <div className="flex justify-between text-xs text-foreground/50">
                <span>Abonado</span>
                <span>{formatARS(booking.amount_paid)}</span>
              </div>
              {booking.balance_due > 0 && (
                <div className="flex justify-between text-sm font-semibold text-amber-600">
                  <span>Saldo pendiente</span>
                  <span>{formatARS(booking.balance_due)}</span>
                </div>
              )}
            </div>

            {/* Payment method */}
            <div className="mt-4 pt-4 border-t border-crema text-sm">
              <p className="text-foreground/50 text-xs mb-1">Método de pago</p>
              <p className="font-semibold text-azul-marino">
                {booking.payment_method === 'mercadopago' ? 'MercadoPago' : 'Transferencia bancaria'}
              </p>
              {/* Bank data (if transfer + details mode) */}
              {booking.payment_method === 'bank_transfer' && booking.bank_data && (
                <div className="mt-3 p-3 bg-crema rounded-lg text-xs space-y-1">
                  {booking.bank_data.bank && <p><span className="font-semibold">Banco:</span> {booking.bank_data.bank}</p>}
                  <p><span className="font-semibold">Titular:</span> {booking.bank_data.holder}</p>
                  {booking.bank_data.cbu && <p className="font-mono"><span className="font-semibold font-sans">CBU:</span> {booking.bank_data.cbu}</p>}
                  <p className="font-mono"><span className="font-semibold font-sans">Alias:</span> {booking.bank_data.alias}</p>
                  {booking.bank_data.cuit && <p><span className="font-semibold">CUIT:</span> {booking.bank_data.cuit}</p>}
                </div>
              )}
            </div>
          </div>

          {/* Guest data */}
          <div className="card p-6">
            <h2 className="font-bold text-azul-marino mb-4 flex items-center gap-2">
              <Users size={18} className="text-azul-cielo" />
              Titular de la reserva
            </h2>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
              <div>
                <p className="text-foreground/50 text-xs mb-0.5">Nombre</p>
                <p className="font-semibold">{booking.guest.first_name} {booking.guest.last_name}</p>
              </div>
              <div>
                <p className="text-foreground/50 text-xs mb-0.5">Email</p>
                <p>{booking.guest.email}</p>
              </div>
              {booking.guest.phone && (
                <div>
                  <p className="text-foreground/50 text-xs mb-0.5">Teléfono</p>
                  <p>{booking.guest.phone}</p>
                </div>
              )}
            </div>
          </div>

          {/* Special requests */}
          {booking.special_requests && (
            <div className="card p-6">
              <h2 className="font-bold text-azul-marino mb-2 text-sm">Pedidos especiales</h2>
              <p className="text-sm text-foreground/70">{booking.special_requests}</p>
            </div>
          )}

          {/* WhatsApp contact button */}
          {booking.whatsapp_url && (
            <div className="text-center">
              <a
                href={booking.whatsapp_url}
                target="_blank"
                rel="noopener noreferrer"
                className="inline-flex items-center gap-2 btn-secondary"
              >
                <PhoneCall size={16} />
                Contactar al hotel por WhatsApp
              </a>
            </div>
          )}

          {/* Back home */}
          <div className="text-center">
            <a href="/" className="text-sm text-azul-cielo hover:text-azul-marino transition-colors">
              ← Volver al inicio
            </a>
          </div>
        </div>
      </div>
    </div>
  );
}

// ── Page wrapper (Suspense required for useSearchParams in App Router) ───────

export default function MiReservaPage() {
  return (
    <Suspense fallback={
      <div className="min-h-screen flex items-center justify-center bg-blanco-roto pt-32">
        <Loader2 size={40} className="animate-spin text-azul-cielo" />
      </div>
    }>
      <MiReservaContent />
    </Suspense>
  );
}
