'use client';

import { useRef } from 'react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import ReviewCard from '@/components/ui/ReviewCard';

/**
 * Real guest reviews sourced from Booking.com (all rated 8/10 or 9/10).
 */
const PLACEHOLDER_REVIEWS = [
  {
    name: 'Rocio',
    rating: 5,
    date: 'Febrero 2026',
    text: 'Confortable y buen ambiente. Lo que hace la diferencia de otros hoteles 2 estrellas es el buen desayuno: yogurt, leche, café, jugo, cereales, tostadas, medialunas, mermelada, manteca y dulce de leche. El personal muy amable y predispuesto. Volvería a alojarme.',
  },
  {
    name: 'Gisela',
    rating: 5,
    date: 'Diciembre 2025',
    text: 'La ubicación es excelente, a 2 cuadras del Paseo Aldrey y a 3 de la playa. El personal es súper atento y amable. El desayuno súper completo y rico.',
  },
  {
    name: 'Cristian',
    rating: 5,
    date: 'Enero 2026',
    text: 'Personal muy atento, desayuno muy bien y limpieza impecable. Todo en orden, no nos faltó nada durante la estadía.',
  },
  {
    name: 'Mónica',
    rating: 4,
    date: 'Diciembre 2025',
    text: 'La atención de su personal es excelente, siempre dispuestos a ayudarte en lo que necesitás. Muy buena experiencia.',
  },
  {
    name: 'Olivia',
    rating: 4,
    date: 'Enero 2026',
    text: 'La ubicación es buena y la gente muy atenta. Todo limpio y cómodo. Lo recomiendo para una escapada a Mar del Plata.',
  },
  {
    name: 'Linares',
    rating: 4,
    date: 'Febrero 2026',
    text: 'Buena y confortable estadía. Destacamos la atención del personal y la limpieza de las habitaciones. Muy bien.',
  },
];

export default function Resenias() {
  const scrollRef = useRef<HTMLDivElement>(null);

  const scroll = (direction: 'left' | 'right') => {
    if (!scrollRef.current) return;
    const amount = 320;
    scrollRef.current.scrollBy({
      left: direction === 'left' ? -amount : amount,
      behavior: 'smooth',
    });
  };

  return (
    <section id="resenias" className="py-16 sm:py-20 bg-blanco-roto">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 className="section-title">Lo que dicen nuestros huéspedes</h2>
        <div className="section-separator" />
        <p className="text-center text-foreground/60 mb-10">
          Reseñas verificadas de <span className="font-bold" style={{ color: '#003580' }}>booking</span><span className="font-bold" style={{ color: '#0071c2' }}>.com</span>
        </p>

        {/* Desktop: grid */}
        <div className="hidden md:grid md:grid-cols-2 lg:grid-cols-3 gap-6">
          {PLACEHOLDER_REVIEWS.map((review, i) => (
            <ReviewCard key={i} {...review} />
          ))}
        </div>

        {/* Mobile: horizontal scroll carousel */}
        <div className="md:hidden relative">
          <div
            ref={scrollRef}
            className="flex gap-4 overflow-x-auto pb-4 snap-x snap-mandatory scrollbar-hide"
            style={{ scrollbarWidth: 'none', msOverflowStyle: 'none' }}
          >
            {PLACEHOLDER_REVIEWS.map((review, i) => (
              <div key={i} className="min-w-[280px] max-w-[300px] snap-start shrink-0">
                <ReviewCard {...review} />
              </div>
            ))}
          </div>

          {/* Scroll buttons */}
          <div className="flex items-center justify-center gap-3 mt-4">
            <button
              onClick={() => scroll('left')}
              className="w-9 h-9 rounded-full border border-azul-marino/20 flex items-center justify-center text-azul-marino hover:bg-crema transition-colors"
              aria-label="Anterior"
            >
              <ChevronLeft size={18} />
            </button>
            <button
              onClick={() => scroll('right')}
              className="w-9 h-9 rounded-full border border-azul-marino/20 flex items-center justify-center text-azul-marino hover:bg-crema transition-colors"
              aria-label="Siguiente"
            >
              <ChevronRight size={18} />
            </button>
          </div>
        </div>

        {/* Booking.com source */}
        <p className="text-center text-xs text-foreground/40 mt-8 flex items-center justify-center gap-2">
          Fuente:
          <a
            href="https://www.booking.com/hotel/ar/las-heras.es.html"
            target="_blank"
            rel="noopener noreferrer"
            className="font-bold hover:opacity-80 transition-opacity"
          >
            <span style={{ color: '#003580' }}>booking</span><span style={{ color: '#0071c2' }}>.com</span>
          </a>
        </p>
      </div>
    </section>
  );
}
