import { Users, BedDouble, BedSingle, Wifi, Bath, Tv, Thermometer, ShowerHead, Phone as PhoneIcon, ArrowRight } from 'lucide-react';
import Link from 'next/link';

const AMENITIES = ['Baño privado', 'Wifi gratis', 'TV cable', 'Calefacción', 'Secador de pelo', 'Teléfono'];

const AMENITY_ICONS: Record<string, React.ReactNode> = {
  'Baño privado': <Bath size={13} />,
  'Wifi gratis': <Wifi size={13} />,
  'TV cable': <Tv size={13} />,
  'Calefacción': <Thermometer size={13} />,
  'Secador de pelo': <ShowerHead size={13} />,
  'Teléfono': <PhoneIcon size={13} />,
};

const STATIC_ROOMS = [
  {
    name: 'Habitación Doble',
    description: 'Ideal para parejas o amigos que buscan comodidad en el centro de Mar del Plata.',
    capacity_adults: 2,
    icon: BedDouble,
    photo: '/habitacion_2ind.jpg',
  },
  {
    name: 'Habitación Triple',
    description: 'Versatilidad y confort para familias o grupos pequeños en espacios amplios.',
    capacity_adults: 3,
    icon: BedSingle,
    photo: '/habitacion_3ind.jpg',
  },
  {
    name: 'Apartamento',
    description: 'Amplio y cómodo, con todo el espacio que necesitás para una estadía prolongada.',
    capacity_adults: 4,
    icon: BedDouble,
    photo: '/habitacion_adorno.jpg',
  },
];

export default function Habitaciones() {
  return (
    <section id="habitaciones" className="py-16 sm:py-20 bg-blanco-roto">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 className="section-title">Nuestras Habitaciones</h2>
        <div className="section-separator" />
        <p className="text-center text-foreground/60 mb-4 max-w-2xl mx-auto">
          Todas las habitaciones cuentan con baño privado y TV por cable. Usá el buscador de arriba para consultar disponibilidad y precios.
        </p>
        <p className="text-center text-sm text-foreground/40 mb-10 max-w-xl mx-auto">
          Desayuno incluido · Sin pago por adelantado · Cancelación con 50% de cargo
        </p>

        <div className="grid grid-cols-1 md:grid-cols-3 gap-6 items-stretch">
          {STATIC_ROOMS.map((room) => {
            return (
              <div key={room.name} className="card flex flex-col overflow-hidden">
                {/* Photo header */}
                <div className="h-52 relative overflow-hidden shrink-0 bg-azul-marino/20">
                  <img
                    src={room.photo}
                    alt={room.name}
                    className="w-full h-full object-cover transition-transform duration-500 hover:scale-105"
                  />
                  <div className="absolute inset-0 bg-gradient-to-t from-azul-marino/60 via-transparent to-transparent" />
                  <h3
                    className="absolute bottom-3 left-4 text-xl font-bold text-white"
                    style={{ fontFamily: 'var(--font-display)' }}
                  >
                    {room.name}
                  </h3>
                </div>

                {/* Content */}
                <div className="p-5 flex flex-col flex-1">
                  {/* Description — fixed height so amenities stay aligned */}
                  <div className="h-[4.5rem] mb-4">
                    <p className="text-sm text-foreground/70 leading-relaxed">
                      {room.description}
                    </p>
                  </div>

                  {/* Amenities — always same items, always aligned */}
                  <div className="flex flex-wrap gap-1.5 mb-4">
                    {AMENITIES.map((amenity) => (
                      <span key={amenity} className="amenity-badge">
                        {AMENITY_ICONS[amenity]}
                        {amenity}
                      </span>
                    ))}
                  </div>

                  {/* Capacity */}
                  <div className="flex items-center gap-4 text-sm text-foreground/70 mt-auto">
                    <span className="flex items-center gap-1.5">
                      <Users size={15} className="text-azul-cielo" />
                      Hasta {room.capacity_adults} huéspedes
                    </span>
                  </div>

                  <div className="border-t border-crema pt-4 mt-4">
                    <a
                      href="#inicio"
                      className="btn-primary w-full block text-center text-sm"
                    >
                      Consultar disponibilidad
                    </a>
                  </div>
                </div>
              </div>
            );
          })}
        </div>

        {/* CTA to detail page */}
        <div className="text-center mt-10">
          <Link
            href="/habitaciones"
            className="inline-flex items-center gap-2 text-azul-cielo hover:text-azul-marino font-semibold transition-colors group"
          >
            Conocé nuestras habitaciones en detalle
            <ArrowRight size={16} className="transition-transform group-hover:translate-x-1" />
          </Link>
        </div>
      </div>
    </section>
  );
}
