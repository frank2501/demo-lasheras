'use client';

import { useState, useEffect, Suspense } from 'react';
import { useSearchParams, useRouter } from 'next/navigation';
import {
  Check,
  CalendarDays,
  Moon,
  Users,
  Star,
  Loader2,
  AlertCircle,
  ArrowLeft,
  Wifi, Thermometer, Tv, Lock, Sun, Wind, Bath, ShowerHead, Coffee
} from 'lucide-react';
import { searchAvailability } from '@/lib/api';
import type { RoomType, AvailabilityResponse } from '@/types/artechia';

/* ── Helpers ─────────────────────────────────────── */

const AMENITY_MAP: Record<string, { label: string; icon: React.ElementType }> = {
  wifi: { label: 'WiFi', icon: Wifi },
  heating: { label: 'Calefacción', icon: Thermometer },
  tv: { label: 'TV Cable', icon: Tv },
  safe: { label: 'Caja fuerte', icon: Lock },
  balcony: { label: 'Balcón', icon: Sun },
  ac: { label: 'Aire Acond.', icon: Wind },
  air_conditioning: { label: 'Aire Acond.', icon: Wind },
  bathroom: { label: 'Baño privado', icon: Bath },
  hairdryer: { label: 'Secador', icon: ShowerHead },
  breakfast: { label: 'Desayuno', icon: Coffee },
};

function getAmenityConfig(key: string) {
  const norm = key.toLowerCase().trim();
  return AMENITY_MAP[norm] || { label: key, icon: Star };
}

function formatARS(amount: number | undefined): string {
  if (amount == null) return '$ —';
  return `$${amount.toLocaleString('es-AR', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}`;
}

function formatDate(iso: string): string {
  if (!iso) return '—';
  const [y, m, d] = iso.split('-');
  return `${d}/${m}/${y}`;
}

import BookingStepper from '@/components/ui/BookingStepper';

/* ── Search Summary Bar ──────────────────────────── */

function SearchSummary({
  checkIn,
  checkOut,
  nights,
  adults,
  children,
  onChangeDates,
}: {
  checkIn: string;
  checkOut: string;
  nights: number;
  adults: number;
  children: number;
  onChangeDates: () => void;
}) {
  return (
    <div className="bg-white rounded-xl border border-gray-100 shadow-sm p-4 sm:p-5 mb-8 flex flex-wrap items-center justify-between gap-4">
      <div className="flex flex-wrap items-center gap-5 sm:gap-8 text-sm">
        <div className="flex items-center gap-2">
          <CalendarDays size={15} className="text-azul-cielo" />
          <div>
            <span className="text-[10px] uppercase tracking-wider text-foreground/40 block">Check-in</span>
            <span className="font-semibold text-azul-marino">{formatDate(checkIn)}</span>
          </div>
        </div>
        <div className="flex items-center gap-2">
          <CalendarDays size={15} className="text-azul-cielo" />
          <div>
            <span className="text-[10px] uppercase tracking-wider text-foreground/40 block">Check-out</span>
            <span className="font-semibold text-azul-marino">{formatDate(checkOut)}</span>
          </div>
        </div>
        <div className="flex items-center gap-2">
          <Moon size={15} className="text-azul-cielo" />
          <div>
            <span className="text-[10px] uppercase tracking-wider text-foreground/40 block">Noches</span>
            <span className="font-semibold text-azul-marino">{nights}</span>
          </div>
        </div>
        <div className="flex items-center gap-2">
          <Users size={15} className="text-azul-cielo" />
          <div>
            <span className="text-[10px] uppercase tracking-wider text-foreground/40 block">Huéspedes</span>
            <span className="font-semibold text-azul-marino">
              {adults}{children > 0 ? ` + ${children}` : ''}
            </span>
          </div>
        </div>
      </div>
      <button
        onClick={onChangeDates}
        className="btn-secondary text-xs uppercase tracking-wider !py-2.5 !px-5"
      >
        Cambiar fechas
      </button>
    </div>
  );
}

/* ── Horizontal Room Card ────────────────────────── */

function RoomResultCard({
  room,
  nights,
  onReserve,
  reserving,
}: {
  room: RoomType;
  nights: number;
  onReserve: (room: RoomType) => void;
  reserving: boolean;
}) {
  const amenities: string[] = room.amenities_json
    ? (() => { try { return JSON.parse(room.amenities_json); } catch { return []; } })()
    : [];
  const pricePerNight = nights > 0 ? Math.round((room.quote?.total || 0) / nights) : 0;

  return (
    <div className="card flex flex-col sm:flex-row overflow-hidden">
      {/* Photo (left side on desktop) */}
      <div className="relative w-full sm:w-72 h-52 sm:h-auto shrink-0 bg-crema overflow-hidden">
        {room.photos && room.photos.length > 0 ? (
          <img
            src={room.photos[0]}
            alt={room.room_type_name}
            className="w-full h-full object-cover"
          />
        ) : (
          <div className="w-full h-full flex items-center justify-center bg-crema min-h-[200px]">
            <span
              className="text-azul-marino/30 text-base"
              style={{ fontFamily: 'var(--font-display)' }}
            >
              {room.room_type_name}
            </span>
          </div>
        )}
      </div>

      {/* Content (middle + right) */}
      <div className="flex flex-col sm:flex-row flex-1 p-5 sm:p-6">
        {/* Info (left content) */}
        <div className="flex-1 pr-0 sm:pr-6 border-b sm:border-b-0 sm:border-r border-gray-100 pb-4 sm:pb-0">
          <h3
            className="text-xl font-bold text-azul-marino mb-2"
            style={{ fontFamily: 'var(--font-display)' }}
          >
            {room.room_type_name}
          </h3>

          {room.description && (
            <p className="text-sm text-foreground/60 mb-3 line-clamp-2">{room.description}</p>
          )}

          <div className="flex items-center gap-1.5 text-sm text-foreground/60 mb-4">
            <Users size={15} className="text-azul-cielo" />
            Hasta {room.max_adults} huéspedes
          </div>

          {/* Amenities */}
          {amenities.length > 0 && (
            <div className="flex flex-wrap gap-1.5 mt-2">
              {amenities.slice(0, 5).map((a: string) => {
                const { label, icon: Icon } = getAmenityConfig(a);
                return (
                  <span key={a} className="amenity-badge text-[11px] px-2.5 py-1">
                    <Icon size={11} className="shrink-0" />
                    {label}
                  </span>
                );
              })}
              {amenities.length > 5 && (
                <span className="amenity-badge text-[11px] px-2.5 py-1">+{amenities.length - 5} más</span>
              )}
            </div>
          )}
        </div>

        {/* Price + CTA (right side) */}
        <div className="flex flex-col items-center justify-center pt-4 sm:pt-0 sm:pl-6 min-w-[160px]">
          <p className="text-3xl font-bold text-azul-marino">
            {formatARS(room.quote?.total)}
          </p>
          <p className="text-xs text-foreground/40 mb-3">
            {formatARS(pricePerNight)} / noche
          </p>
            <button
              onClick={() => onReserve(room)}
              className="btn-primary uppercase tracking-wider text-sm !px-8 !py-3 mt-2"
              disabled={!room.bookable || reserving}
            >
              {reserving ? 'Reservando...' : 'Reservar'}
          </button>
        </div>
      </div>
    </div>
  );
}

/* ── Skeleton ────────────────────────────────────── */

function SkeletonRoom() {
  return (
    <div className="card flex flex-col sm:flex-row overflow-hidden animate-pulse">
      <div className="w-full sm:w-72 h-52 sm:h-48 bg-crema shrink-0" />
      <div className="flex flex-col sm:flex-row flex-1 p-6">
        <div className="flex-1 pr-6">
          <div className="h-6 w-48 bg-crema rounded mb-3" />
          <div className="h-4 w-72 bg-crema rounded mb-2" />
          <div className="h-4 w-32 bg-crema rounded mb-4" />
          <div className="flex gap-2">
            <div className="h-6 w-20 bg-crema rounded-full" />
            <div className="h-6 w-20 bg-crema rounded-full" />
          </div>
        </div>
        <div className="flex flex-col items-center justify-center min-w-[160px] pt-4 sm:pt-0 sm:pl-6">
          <div className="h-8 w-28 bg-crema rounded mb-2" />
          <div className="h-4 w-20 bg-crema rounded mb-4" />
          <div className="h-10 w-28 bg-crema rounded" />
        </div>
      </div>
    </div>
  );
}

/* ── Main Page ───────────────────────────────────── */

function DisponibilidadContent() {
  const searchParams = useSearchParams();
  const router = useRouter();

  const checkIn = searchParams.get('check_in') || '';
  const checkOut = searchParams.get('check_out') || '';
  const adults = Number(searchParams.get('adults') || 2);
  const children = Number(searchParams.get('children') || 0);

  const [rooms, setRooms] = useState<RoomType[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [reserving, setReserving] = useState(false);

  // Calculate nights
  const nights = (() => {
    if (!checkIn || !checkOut) return 0;
    const d1 = new Date(checkIn);
    const d2 = new Date(checkOut);
    return Math.max(0, Math.round((d2.getTime() - d1.getTime()) / (1000 * 60 * 60 * 24)));
  })();

  useEffect(() => {
    if (!checkIn || !checkOut) {
      setError('Faltan las fechas de búsqueda. Volvé al inicio para buscar disponibilidad.');
      setLoading(false);
      return;
    }

    let cancelled = false;

    const fetchAvailability = async () => {
      setLoading(true);
      setError('');
      try {
        const data: AvailabilityResponse = await searchAvailability(checkIn, checkOut, adults, children);
        if (cancelled) return;
        console.log('[Availability Response]', data);

        if (data.error) {
          const msg =
            data.error === 'NO_AVAILABILITY'
              ? 'No hay habitaciones disponibles para esas fechas. Probá con otras fechas.'
              : data.error === 'DATES_CLOSED'
              ? 'El hotel no tiene disponibilidad en ese período.'
              : data.error === 'BOOKINGS_DISABLED'
              ? 'Las reservas están temporalmente pausadas. Contactanos por teléfono.'
              : data.message || data.error;
          setError(msg);
          return;
        }

        setRooms(data.room_types || []);
        if (!data.room_types || data.room_types.length === 0) {
          setError('No hay habitaciones disponibles para esas fechas. Probá con otras fechas.');
        }
      } catch (err) {
        if (cancelled) return;
        console.error('[Availability Error]', err);
        setError('No pudimos conectar con el servidor. Intentá de nuevo más tarde.');
      } finally {
        if (!cancelled) setLoading(false);
      }
    };

    fetchAvailability();

    return () => { cancelled = true; };
  }, [checkIn, checkOut, adults, children]);

  const handleReserve = (room: RoomType) => {
    if (reserving) return;
    setReserving(true);
    sessionStorage.setItem('checkout_data', JSON.stringify({
      room_type_id: room.room_type_id,
      rate_plan_id: room.rate_plan_id,
      check_in: checkIn,
      check_out: checkOut,
      adults,
      children,
      room_name: room.room_type_name,
      total: room.quote?.total || 0,
      currency: room.quote?.currency || 'ARS',
      nights,
    }));
    router.push('/checkout');
  };

  return (
    <div className="min-h-screen bg-blanco-roto pt-32">
      <div className="max-w-5xl mx-auto px-4 sm:px-6">
        {/* Stepper */}
        <BookingStepper current={2} />

        {/* Search summary */}
        <SearchSummary
          checkIn={checkIn}
          checkOut={checkOut}
          nights={nights}
          adults={adults}
          children={children}
          onChangeDates={() => router.push('/#inicio')}
        />

        {/* Title */}
        <h2
          className="text-2xl font-bold text-azul-marino mb-6"
          style={{ fontFamily: 'var(--font-display)' }}
        >
          Habitaciones disponibles
        </h2>

        {/* Error */}
        {error && !loading && (
          <div className="mb-8 flex items-center gap-3 text-azul-marino bg-crema p-5 rounded-lg border border-dorado/30">
            <AlertCircle size={20} className="text-dorado shrink-0" />
            <p className="text-sm">{error}</p>
          </div>
        )}

        {/* Loading skeletons */}
        {loading && (
          <div className="space-y-6">
            <SkeletonRoom />
            <SkeletonRoom />
          </div>
        )}

        {/* Room cards */}
        {!loading && rooms.length > 0 && (
          <div className="space-y-6 pb-16">
            {rooms.map((room) => (
              <RoomResultCard
                key={room.room_type_id}
                room={room}
                nights={nights}
                onReserve={handleReserve}
                reserving={reserving}
              />
            ))}
          </div>
        )}

        {/* Back button when error */}
        {error && !loading && (
          <button
            onClick={() => router.push('/#inicio')}
            className="btn-primary mt-4 mb-16 flex items-center gap-2"
          >
            <ArrowLeft size={16} />
            Volver a buscar
          </button>
        )}
      </div>
    </div>
  );
}

export default function DisponibilidadPage() {
  return (
    <Suspense
      fallback={
        <div className="min-h-screen flex items-center justify-center bg-blanco-roto pt-32">
          <div className="text-center">
            <Loader2 size={40} className="animate-spin text-azul-cielo mx-auto mb-4" />
            <p className="text-azul-marino font-semibold">Buscando disponibilidad...</p>
          </div>
        </div>
      }
    >
      <DisponibilidadContent />
    </Suspense>
  );
}
