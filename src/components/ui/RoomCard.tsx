import Image from 'next/image';
import type { RoomType } from '@/types/artechia';
import { 
  Users, Check, X, 
  Wifi, Thermometer, Tv, Lock, 
  Sun, Wind, Bath, ShowerHead, Coffee 
} from 'lucide-react';

const AMENITY_MAP: Record<string, { label: string; icon: React.ElementType }> = {
  wifi: { label: 'WiFi', icon: Wifi },
  heating: { label: 'Calefacción', icon: Thermometer },
  tv: { label: 'TV Cable', icon: Tv },
  safe: { label: 'Caja Fuerte', icon: Lock },
  balcony: { label: 'Balcón', icon: Sun },
  ac: { label: 'Aire Acond.', icon: Wind },
  air_conditioning: { label: 'Aire Acond.', icon: Wind },
  bathroom: { label: 'Baño Privado', icon: Bath },
  hairdryer: { label: 'Secador', icon: ShowerHead },
  breakfast: { label: 'Desayuno', icon: Coffee },
};

function getAmenityConfig(key: string) {
  const norm = key.toLowerCase().trim();
  return AMENITY_MAP[norm] || { label: key, icon: Check };
}

interface RoomCardProps {
  room: RoomType;
  onReserve: (room: RoomType) => void;
}

function formatARS(amount: number | undefined): string {
  if (amount == null) return '$ —';
  return `$ ${amount.toLocaleString('es-AR', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}`;
}

export default function RoomCard({ room, onReserve }: RoomCardProps) {
  const amenities = room.amenities_json ? (() => {
    try { return JSON.parse(room.amenities_json); } catch { return []; }
  })() : [];

  return (
    <div className="card flex flex-col">
      {/* Photo */}
      <div className="relative h-52 sm:h-56 bg-crema overflow-hidden">
        {room.photos && room.photos.length > 0 ? (
          <Image
            src={room.photos[0]}
            alt={room.room_type_name}
            fill
            sizes="(max-width: 768px) 100vw, 33vw"
            className="object-cover"
          />
        ) : (
          <div className="w-full h-full flex items-center justify-center bg-crema">
            <span
              className="text-azul-marino/40 text-lg"
              style={{ fontFamily: 'var(--font-display)' }}
            >
              {room.room_type_name}
            </span>
          </div>
        )}
        {!room.bookable && (
          <div className="absolute top-3 right-3 bg-red-500/90 text-white text-xs font-semibold px-3 py-1 rounded-full">
            No disponible
          </div>
        )}
      </div>

      {/* Content */}
      <div className="p-5 flex flex-col flex-1">
        <h3
          className="text-xl font-bold text-azul-marino mb-2"
          style={{ fontFamily: 'var(--font-display)' }}
        >
          {room.room_type_name}
        </h3>

        <p className="text-sm text-foreground/70 mb-4 leading-relaxed line-clamp-3">
          {room.description || `Capacidad: hasta ${room.max_adults} adultos`}
        </p>

        {/* Capacity */}
        <div className="flex items-center gap-4 mb-4 text-sm text-foreground/70">
          <span className="flex items-center gap-1.5">
            <Users size={15} className="text-azul-cielo" />
            Hasta {room.max_adults} huéspedes
          </span>
        </div>

        {/* Amenities */}
        {amenities.length > 0 && (
          <div className="flex flex-wrap gap-1.5 mb-5">
            {amenities.slice(0, 6).map((amenityKey: string) => {
              const { label, icon: Icon } = getAmenityConfig(amenityKey);
              return (
                <span key={amenityKey} className="amenity-badge">
                  <Icon size={12} />
                  {label}
                </span>
              );
            })}
            {amenities.length > 6 && (
              <span className="amenity-badge">+{amenities.length - 6} más</span>
            )}
          </div>
        )}

        {/* Spacer */}
        <div className="flex-1" />

        {/* Price & CTA */}
        <div className="border-t border-crema pt-4 mt-2">
          <div className="flex items-end justify-between mb-3">
            <div>
              <p className="text-2xl font-bold text-azul-marino">
                {formatARS(room.quote?.total)}
              </p>
              <p className="text-xs text-foreground/50">
                {room.quote?.nights_count} {room.quote?.nights_count === 1 ? 'noche' : 'noches'} · {room.quote?.currency}
              </p>
            </div>
          </div>

          {room.bookable ? (
            <button
              onClick={() => onReserve(room)}
              className="btn-primary w-full"
            >
              Reservar
            </button>
          ) : (
            <button disabled className="btn-primary w-full opacity-50 cursor-not-allowed flex items-center justify-center gap-2">
              <X size={16} />
              No disponible
            </button>
          )}
        </div>
      </div>
    </div>
  );
}
