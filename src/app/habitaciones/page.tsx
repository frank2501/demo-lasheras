'use client';

import { useState } from 'react';
import {
  Users,
  Wifi,
  Bath,
  Tv,
  Thermometer,
  ShowerHead,
  Phone as PhoneIcon,
  Wind,
  ArrowLeft,
  Coffee,
  Maximize,
  X,
  ChevronLeft,
  ChevronRight,
  Expand
} from 'lucide-react';
import Link from 'next/link';

/* ─── Room data ───────────────────────────────────────── */

const ROOMS = [
  {
    name: 'Habitación Doble',
    tagline: 'Ideal para parejas',
    description:
      'Diseñada para parejas o amigos que buscan comodidad y tranquilidad en el centro de Mar del Plata. Ambientes luminosos con decoración cálida y todos los servicios necesarios para un descanso completo.',
    detailedFeatures: [
      'Cama matrimonial o 2 camas individuales',
      'Baño privado con ducha y amenities',
      'Ropa de cama y toallas de algodón',
      'Vista al jardín interior',
    ],
    capacity_adults: 2,
    size: '18 m²',
    photos: ['/habitacion_2ind.jpg', '/habitacion_2indv.jpg'], // Solo fotos de doble
    amenities: [
      { icon: Bath, label: 'Baño privado' },
      { icon: Wifi, label: 'WiFi gratis' },
      { icon: Tv, label: 'TV por cable' },
      { icon: Thermometer, label: 'Calefacción' },
      { icon: ShowerHead, label: 'Secador de pelo' },
      { icon: PhoneIcon, label: 'Teléfono' },
    ],
    reverse: false,
  },
  {
    name: 'Habitación Triple',
    tagline: '',
    description:
      'Versatilidad y confort para familias o grupos pequeños. Espacios amplios con todo lo necesario para que cada huésped se sienta como en casa.',
    detailedFeatures: [
      '3 camas individuales o 1 matrimonial + 1 individual',
      'Baño privado con ducha y amenities',
      'Amplio espacio con mobiliario familiar',
      'Ropa de cama y toallas de algodón',
    ],
    capacity_adults: 3,
    size: '24 m²',
    photos: ['/habitacion_3ind.jpg', '/habitacion_3indv.jpg'], // Solo fotos de triple
    amenities: [
      { icon: Bath, label: 'Baño privado' },
      { icon: Wifi, label: 'WiFi gratis' },
      { icon: Tv, label: 'TV por cable' },
      { icon: Thermometer, label: 'Calefacción' },
      { icon: Wind, label: 'Ventilador' },
      { icon: PhoneIcon, label: 'Teléfono' },
    ],
    badge: 'Recomendado Familias',
    reverse: true,
  },
  {
    name: 'Apartamento',
    tagline: 'Tu hogar en Mar del Plata',
    description:
      'Amplio y cómodo, con todo el espacio que necesitás para una estadía prolongada. Ideal para familias numerosas o grupos que buscan independencia y confort sin renunciar a los servicios del hotel.',
    detailedFeatures: [
      '1 cama matrimonial + 2 camas individuales',
      'Baño privado completo con amenities',
      'Espacio extra para equipaje y convivencia',
      'Mayor amplitud y privacidad',
    ],
    capacity_adults: 4,
    size: '32 m²',
    photos: ['/habitacion_adorno.jpg'], // Solo foto de apartamento (puedes agregar más luego)
    amenities: [
      { icon: Bath, label: 'Baño privado' },
      { icon: Wifi, label: 'WiFi gratis' },
      { icon: Tv, label: 'TV por cable' },
      { icon: Thermometer, label: 'Calefacción' },
      { icon: Wind, label: 'Ventilador' },
      { icon: ShowerHead, label: 'Secador de pelo' },
    ],
    reverse: false,
  },
];

/* ─── Page ─────────────────────────────────────────────── */

export default function HabitacionesPage() {
  const [activeGallery, setActiveGallery] = useState<string[] | null>(null);
  const [currentImageIndex, setCurrentImageIndex] = useState(0);

  const openGallery = (photos: string[]) => {
    setActiveGallery(photos);
    setCurrentImageIndex(0);
    // Prevent scrolling behind modal
    document.body.style.overflow = 'hidden';
  };

  const closeGallery = () => {
    setActiveGallery(null);
    document.body.style.overflow = 'unset';
  };

  const nextImage = (e: React.MouseEvent) => {
    e.stopPropagation();
    if (activeGallery) {
      setCurrentImageIndex((prev) => (prev + 1) % activeGallery.length);
    }
  };

  const prevImage = (e: React.MouseEvent) => {
    e.stopPropagation();
    if (activeGallery) {
      setCurrentImageIndex((prev) => (prev - 1 + activeGallery.length) % activeGallery.length);
    }
  };

  return (
    <div className="bg-blanco-roto min-h-screen">
      {/* ── Hero header ── */}
      <header className="pt-28 sm:pt-36 pb-16 sm:pb-20 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
        <Link
          href="/"
          className="inline-flex items-center gap-2 text-sm font-semibold text-azul-cielo hover:text-azul-marino transition-colors mb-8"
        >
          <ArrowLeft size={16} />
          Volver al inicio
        </Link>
        <title>Habitaciones | Las Heras Hotel — Mar del Plata</title>
        <meta name="description" content="Conocé en detalle nuestras habitaciones: Doble, Triple y Apartamento. Todas con baño privado, TV cable, WiFi y desayuno incluido. A pasos del mar." />

        <h1
          className="text-4xl sm:text-5xl lg:text-6xl font-bold text-azul-marino mb-6 leading-tight max-w-3xl"
          style={{ fontFamily: 'var(--font-display)' }}
        >
          Nuestras{' '}
          <span className="bg-crema px-3 py-1 inline-block">Habitaciones</span>
        </h1>
        <p className="text-lg text-foreground/60 max-w-xl leading-relaxed">
          Todas nuestras habitaciones cuentan con baño privado, TV por cable,
          WiFi gratis y desayuno artesanal incluido. Diseñadas para tu descanso
          en el corazón de Mar del Plata.
        </p>

        {/* Quick info badges */}
        <div className="flex flex-wrap gap-3 mt-8">
          <span className="amenity-badge text-sm">
            <Coffee size={14} />
            Desayuno incluido
          </span>
          <span className="amenity-badge text-sm">
            <Wifi size={14} />
            WiFi gratis
          </span>
          <span className="amenity-badge text-sm">
            ✕ Sin pago por adelantado
          </span>
        </div>
      </header>

      {/* ── Room cards ── */}
      <main className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pb-24 space-y-24 sm:space-y-32">
        {ROOMS.map((room) => {
          const roomId = room.name.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "").replace(/\s+/g, '-');
          return (
          <article
            key={room.name}
            id={roomId}
            className={`relative flex flex-col items-center gap-8 lg:gap-12 scroll-mt-32 ${
              room.reverse ? 'lg:flex-row-reverse' : 'lg:flex-row'
            }`}
          >
            {/* ── Image block ── */}
            <div 
              className="relative w-full lg:w-[58%] overflow-hidden rounded-xl shadow-lg group shrink-0 cursor-pointer"
              onClick={() => openGallery(room.photos)}
            >
              <img
                src={room.photos[0]}
                alt={room.name}
                className="w-full h-[320px] sm:h-[380px] lg:h-[420px] object-cover transition-transform duration-700 group-hover:scale-105"
              />
              {/* Gradient overlay just at the bottom for aesthetics, no badges */}
              <div className="absolute inset-0 bg-gradient-to-t from-azul-marino/20 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300" />
            </div>

            {/* ── Content card ── */}
            <div
              className={`w-full lg:w-[38%] bg-white rounded-xl p-5 lg:p-6 shadow-lg flex flex-col`}
              style={{
                boxShadow: '0 8px 32px rgba(27, 58, 92, 0.08)',
              }}
            >
              {/* Top info row: Recommendation Badge + Size/Capacity */}
              <div className="flex flex-wrap items-center justify-between gap-3 mb-3">
                {room.badge ? (
                  <div className="bg-dorado/15 text-dorado px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider">
                    {room.badge}
                  </div>
                ) : (
                  <div /> /* Empty div for flex spacing if no badge */
                )}

                <div className="flex gap-2">
                  <div className="bg-crema px-2.5 py-1 rounded-full flex items-center gap-1.5 border border-azul-marino/5">
                    <Users size={12} className="text-azul-cielo" />
                    <span className="text-[11px] font-semibold text-azul-marino">
                      Hasta {room.capacity_adults}
                    </span>
                  </div>
                  <div className="bg-crema px-2.5 py-1 rounded-full flex items-center gap-1.5 border border-azul-marino/5">
                    <Maximize size={12} className="text-azul-cielo" />
                    <span className="text-[11px] font-semibold text-azul-marino">
                      {room.size}
                    </span>
                  </div>
                </div>
              </div>

              {/* Room name */}
              <h2
                className="text-2xl sm:text-3xl font-bold text-azul-marino mb-1"
                style={{ fontFamily: 'var(--font-display)' }}
              >
                {room.name}
              </h2>

              {/* Tagline */}
              {room.tagline && (
                <p className="text-azul-cielo font-semibold text-sm mb-3">
                  {room.tagline}
                </p>
              )}

              {/* Description */}
              <p className="text-sm text-foreground/65 leading-relaxed mb-4">
                {room.description}
              </p>

              {/* Detail features */}
              <div className="mb-4">
                <h3 className="text-xs font-bold text-azul-marino uppercase tracking-wider mb-2">
                  Características
                </h3>
                <ul className="space-y-1.5">
                  {room.detailedFeatures.map((feat) => (
                    <li
                      key={feat}
                      className="flex items-start gap-2 text-[13px] text-foreground/70"
                    >
                      <span className="text-dorado">•</span>
                      {feat}
                    </li>
                  ))}
                </ul>
              </div>

              {/* Amenities grid */}
              <div className="flex flex-wrap gap-2 mb-5">
                {room.amenities.map(({ icon: Icon, label }) => (
                  <span key={label} className="amenity-badge">
                    <Icon size={13} />
                    {label}
                  </span>
                ))}
              </div>

              {/* CTA */}
              <div className="border-t border-crema pt-4 mt-auto">
                <a
                  href="/#inicio"
                  className="btn-primary w-full block text-center py-2.5 text-sm"
                >
                  Consultar disponibilidad
                </a>
              </div>
            </div>
          </article>
          );
        })}
      </main>

      {/* ── Bottom CTA ── */}
      <section className="bg-azul-marino py-16 sm:py-20">
        <div className="max-w-3xl mx-auto px-4 sm:px-6 text-center">
          <h2
            className="text-3xl sm:text-4xl font-bold text-white mb-4"
            style={{ fontFamily: 'var(--font-display)' }}
          >
            ¿Listo para reservar?
          </h2>
          <p className="text-white/70 mb-8 leading-relaxed">
            Verificá la disponibilidad de tu habitación ideal y asegurá tu
            estadía en Mar del Plata. Sin pago por adelantado.
          </p>
          <Link
            href="/#inicio"
            className="inline-flex items-center gap-2 bg-dorado text-white px-8 py-4 rounded-lg font-bold text-lg hover:bg-dorado/90 transition-colors shadow-lg"
          >
            Buscar disponibilidad
          </Link>
        </div>
      </section>

      {/* ── Image Gallery Modal ── */}
      {activeGallery && (
        <div className="fixed inset-0 z-[100] flex items-center justify-center bg-black/90 backdrop-blur-sm p-4 sm:p-8">
          {/* Close button */}
          <button
            onClick={closeGallery}
            className="absolute top-4 right-4 sm:top-8 sm:right-8 text-white/70 hover:text-white p-2 transition-colors z-[110]"
          >
            <X size={32} />
          </button>

          {/* Main image container */}
          <div className="relative w-full max-w-6xl max-h-[85vh] flex items-center justify-center" onClick={(e) => e.stopPropagation()}>
            <img
              src={activeGallery[currentImageIndex]}
              alt={`Imagen ${currentImageIndex + 1} de la galería`}
              className="max-w-full max-h-[85vh] object-contain rounded-lg shadow-2xl"
            />
            
            {/* Gallery controls */}
            {activeGallery.length > 1 && (
              <>
                <button
                  onClick={prevImage}
                  className="absolute left-4 top-1/2 -translate-y-1/2 bg-black/50 hover:bg-black/80 text-white p-3 rounded-full backdrop-blur-md transition-colors"
                >
                  <ChevronLeft size={24} />
                </button>
                <button
                  onClick={nextImage}
                  className="absolute right-4 top-1/2 -translate-y-1/2 bg-black/50 hover:bg-black/80 text-white p-3 rounded-full backdrop-blur-md transition-colors"
                >
                  <ChevronRight size={24} />
                </button>
                
                {/* Dots indicator */}
                <div className="absolute bottom-4 left-1/2 -translate-x-1/2 flex gap-2">
                  {activeGallery.map((_, idx) => (
                    <button
                      key={idx}
                      onClick={(e) => { e.stopPropagation(); setCurrentImageIndex(idx); }}
                      className={`w-2.5 h-2.5 rounded-full transition-colors ${
                        idx === currentImageIndex ? 'bg-white' : 'bg-white/40 hover:bg-white/60'
                      }`}
                    />
                  ))}
                </div>
              </>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
