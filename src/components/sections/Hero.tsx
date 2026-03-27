'use client';

import { useState, useRef, useEffect, FormEvent } from 'react';
import { useRouter } from 'next/navigation';
import { Search, AlertCircle, CalendarDays } from 'lucide-react';
import AvailabilityCalendar from '@/components/ui/AvailabilityCalendar';

export default function Hero() {
  const router = useRouter();
  const [checkIn, setCheckIn] = useState('');
  const [checkOut, setCheckOut] = useState('');
  const [adults, setAdults] = useState(2);
  const [children, setChildren] = useState(0);
  const [loading, setLoading] = useState(false);
  const [validationError, setValidationError] = useState('');
  const [calendarOpen, setCalendarOpen] = useState(false);
  const [calendarDropUp, setCalendarDropUp] = useState(true);
  const calendarRef = useRef<HTMLDivElement>(null);
  const calendarDropdownRef = useRef<HTMLDivElement>(null);
  const calendarTriggerRef = useRef<HTMLButtonElement>(null);

  // Close calendar on outside click
  useEffect(() => {
    const handler = (e: MouseEvent) => {
      if (calendarRef.current && !calendarRef.current.contains(e.target as Node)) {
        setCalendarOpen(false);
      }
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, []);

  // Auto-close calendar when both dates selected
  useEffect(() => {
    if (checkIn && checkOut) {
      setCalendarOpen(false);
    }
  }, [checkIn, checkOut]);

  // Decide direction when calendar opens
  const openCalendar = () => {
    if (!calendarOpen && calendarTriggerRef.current) {
      const rect = calendarTriggerRef.current.getBoundingClientRect();
      const spaceBelow = window.innerHeight - rect.bottom;
      // Calendar is roughly 380px tall — open down if enough room, otherwise up
      setCalendarDropUp(spaceBelow < 420);
    }
    setCalendarOpen((prev) => !prev);
  };

  // Auto-scroll calendar into view when opened
  useEffect(() => {
    if (calendarOpen && calendarDropdownRef.current) {
      setTimeout(() => {
        calendarDropdownRef.current?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }, 50);
    }
  }, [calendarOpen]);

  const handleSearch = (e: FormEvent) => {
    e.preventDefault();
    setValidationError('');

    if (!checkIn || !checkOut) {
      setValidationError('Por favor, seleccioná las fechas de check-in y check-out.');
      return;
    }
    if (checkOut <= checkIn) {
      setValidationError('La fecha de check-out debe ser posterior al check-in.');
      return;
    }

    setLoading(true);
    const params = new URLSearchParams({
      check_in: checkIn,
      check_out: checkOut,
      adults: String(adults),
      children: String(children),
    });
    router.push(`/disponibilidad?${params.toString()}`);
  };

  const MESES = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

  const formatDateShort = (iso: string, placeholder: string) => {
    if (!iso) return placeholder;
    const [, m, d] = iso.split('-');
    return `${parseInt(d)} ${MESES[parseInt(m) - 1]}`;
  };

  const nights = (() => {
    if (!checkIn || !checkOut) return 0;
    const d1 = new Date(checkIn);
    const d2 = new Date(checkOut);
    return Math.max(0, Math.round((d2.getTime() - d1.getTime()) / (1000 * 60 * 60 * 24)));
  })();

  return (
    <section
      id="inicio"
      className="relative min-h-screen flex items-center justify-center"
    >
      {/* Background image */}
      <div className="absolute inset-0">
        <img
          src="/hero.jpg"
          alt=""
          className="w-full h-full object-cover"
        />
        {/* Dark overlay for readability */}
        <div className="absolute inset-0 bg-black/50" />
        {/* Extra gradient: darker at top (navbar area) and bottom (search form) */}
        <div className="absolute inset-0 bg-gradient-to-b from-black/40 via-transparent to-black/60" />
      </div>

      {/* Content */}
      <div className="relative z-10 text-center px-4 w-full max-w-5xl mx-auto pt-24 pb-16">
        {/* Decorative line */}
        <div className="flex items-center justify-center gap-4 mb-6">
          <div className="w-12 h-px bg-dorado" />
          <span className="text-dorado text-xs uppercase tracking-[0.3em] font-semibold">
            Mar del Plata, Buenos Aires
          </span>
          <div className="w-12 h-px bg-dorado" />
        </div>

        <h1
          className="text-4xl sm:text-5xl md:text-6xl lg:text-7xl font-bold text-white mb-5 leading-tight"
          style={{ fontFamily: 'var(--font-display)' }}
        >
          Las Heras Hotel
        </h1>

        <p className="text-lg sm:text-xl text-white/85 mb-3 font-light tracking-wide">
          A pasos del mar, en el corazón de Mar del Plata
        </p>

        <p className="text-sm text-white/60 mb-16">
          Calidez y hospitalidad argentina desde siempre
        </p>

        {/* Search form */}
        <div className="relative max-w-3xl mx-auto" ref={calendarRef}>
          <form
            onSubmit={handleSearch}
            className="bg-white/95 backdrop-blur-sm rounded-xl p-5 sm:p-7 shadow-xl"
          >
            <div className="grid grid-cols-2 sm:grid-cols-8 gap-4 items-end">
              {/* Fechas (combined check-in / check-out) */}
              <div className="text-left col-span-2 sm:col-span-4">
                <label className="form-label text-azul-marino/70 flex items-center justify-between">
                  Fechas
                  {nights > 0 && (
                    <span className="text-[10px] font-semibold text-azul-cielo bg-azul-cielo/10 px-2 py-0.5 rounded-full">
                      {nights} {nights === 1 ? 'noche' : 'noches'}
                    </span>
                  )}
                </label>
                <button
                  ref={calendarTriggerRef}
                  type="button"
                  onClick={openCalendar}
                  className={`form-input w-full text-left text-sm font-medium cursor-pointer hover:border-azul-cielo/50 transition-colors flex items-center gap-2 ${
                    checkIn ? 'text-azul-marino' : 'text-gray-300'
                  }`}
                >
                  <CalendarDays size={14} className="text-azul-cielo shrink-0" />
                  {formatDateShort(checkIn, 'Check-in')} - {formatDateShort(checkOut, 'Check-out')}
                </button>
              </div>

              {/* Adults */}
              <div className="text-left">
                <label className="form-label text-azul-marino/70">Adultos</label>
                <select
                  className="form-input"
                  value={adults}
                  onChange={(e) => setAdults(Number(e.target.value))}
                >
                  {[1, 2, 3, 4, 5, 6].map((n) => (
                    <option key={n} value={n}>{n}</option>
                  ))}
                </select>
              </div>

              {/* Children */}
              <div className="text-left">
                <label className="form-label text-azul-marino/70">Menores</label>
                <select
                  className="form-input"
                  value={children}
                  onChange={(e) => setChildren(Number(e.target.value))}
                >
                  {[0, 1, 2, 3, 4].map((n) => (
                    <option key={n} value={n}>{n}</option>
                  ))}
                </select>
              </div>

              {/* Search button */}
              <button type="submit" className="btn-primary w-full col-span-2 sm:col-span-2" disabled={loading}>
                {loading ? (
                  <>Buscando...</>
                ) : (
                  <>
                    <Search size={16} />
                    Buscar
                  </>
                )}
              </button>
            </div>

            {validationError && (
              <div className="mt-4 flex items-center gap-2 text-red-600 text-sm bg-red-50 p-3 rounded-md">
                <AlertCircle size={16} className="shrink-0" />
                {validationError}
              </div>
            )}
          </form>

          {/* Calendar dropdown — direction determined dynamically */}
          {calendarOpen && (
            <div
              ref={calendarDropdownRef}
              className={`absolute left-0 right-0 z-50 ${
                calendarDropUp
                  ? 'bottom-full mb-2 max-h-[calc(100vh-5.5rem)] overflow-y-auto'
                  : 'top-full mt-2'
              }`}
            >
              <AvailabilityCalendar
                checkIn={checkIn}
                checkOut={checkOut}
                onCheckInChange={setCheckIn}
                onCheckOutChange={setCheckOut}
              />
            </div>
          )}
        </div>
      </div>
    </section>
  );
}
