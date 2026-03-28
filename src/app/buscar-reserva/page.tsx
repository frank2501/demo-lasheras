'use client';

import { useState, FormEvent, Suspense } from 'react';
import { useRouter } from 'next/navigation';
import { Search, Loader2, AlertCircle, Mail, Hash } from 'lucide-react';
import { findBooking } from '@/lib/api';

function BuscarReservaContent() {
  const router = useRouter();
  const [code, setCode]       = useState('');
  const [email, setEmail]     = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError]     = useState('');

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();
    setError('');
    setLoading(true);

    try {
      const { booking_code, access_token } = await findBooking(code.trim().toUpperCase(), email.trim());
      router.push(`/mi-reserva?code=${encodeURIComponent(booking_code)}&token=${encodeURIComponent(access_token)}`);
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'No pudimos encontrar tu reserva. Verificá los datos.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-blanco-roto flex items-center justify-center pt-32 pb-16 px-4">
      <div className="w-full max-w-md">

        {/* Header */}
        <div className="text-center mb-8">
          <div className="w-14 h-14 rounded-full bg-azul-marino/10 flex items-center justify-center mx-auto mb-4">
            <Search size={24} className="text-azul-marino" />
          </div>
          <h1
            className="text-3xl font-bold text-azul-marino mb-2"
            style={{ fontFamily: 'var(--font-display)' }}
          >
            Ver mi reserva
          </h1>
          <p className="text-sm text-foreground/60">
            Ingresá tu código de reserva y el email con el que reservaste.
          </p>
        </div>

        {/* Form */}
        <div className="card p-8">
          <form onSubmit={handleSubmit} className="space-y-5">
            {/* Booking code */}
            <div>
              <label htmlFor="booking-code" className="block text-sm font-semibold text-azul-marino mb-1.5">
                Código de reserva
              </label>
              <div className="relative">
                <Hash size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-foreground/40" />
                <input
                  id="booking-code"
                  type="text"
                  className="form-input !pl-10 font-mono tracking-wider uppercase"
                  placeholder="LH260312AB"
                  value={code}
                  onChange={(e) => { setCode(e.target.value.toUpperCase()); setError(''); }}
                  required
                  autoComplete="off"
                  autoFocus
                />
              </div>
            </div>

            {/* Email */}
            <div>
              <label htmlFor="booking-email" className="block text-sm font-semibold text-azul-marino mb-1.5">
                Email de la reserva
              </label>
              <div className="relative">
                <Mail size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-foreground/40" />
                <input
                  id="booking-email"
                  type="email"
                  className="form-input !pl-10"
                  placeholder="tu@email.com"
                  value={email}
                  onChange={(e) => { setEmail(e.target.value); setError(''); }}
                  required
                  autoComplete="email"
                />
              </div>
            </div>

            {/* Error */}
            {error && (
              <div className="flex items-start gap-2 bg-red-50 border border-red-200 rounded-lg p-3">
                <AlertCircle size={16} className="text-red-500 shrink-0 mt-0.5" />
                <p className="text-sm text-red-700">{error}</p>
              </div>
            )}

            {/* Submit */}
            <button
              type="submit"
              disabled={loading || !code.trim() || !email.trim()}
              className="btn-primary w-full flex items-center justify-center gap-2"
            >
              {loading
                ? <><Loader2 size={16} className="animate-spin" /> Buscando...</>
                : <><Search size={16} /> Buscar reserva</>
              }
            </button>
          </form>
        </div>

        {/* Back link */}
        <div className="text-center mt-6">
          <a href="/" className="text-sm text-azul-cielo hover:text-azul-marino transition-colors">
            ← Volver al inicio
          </a>
        </div>
      </div>
    </div>
  );
}

export default function BuscarReservaPage() {
  return (
    <Suspense fallback={
      <div className="min-h-screen flex items-center justify-center bg-blanco-roto pt-32">
        <Loader2 size={40} className="animate-spin text-azul-cielo" />
      </div>
    }>
      <BuscarReservaContent />
    </Suspense>
  );
}
