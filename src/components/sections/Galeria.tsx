'use client';

import { useState } from 'react';
import Lightbox from '@/components/ui/Lightbox';

// Real gallery images from /public
const GALLERY_IMAGES = [
  { src: '/fachada.jpg',          alt: 'Fachada del hotel' },
  { src: '/habitacion_2ind.jpg',  alt: 'Habitación doble — 2 individuales' },
  { src: '/habitacion_2indv.jpg', alt: 'Habitación doble — vista alternativa' },
  { src: '/habitacion_3ind.jpg',  alt: 'Habitación triple — 3 individuales' },
  { src: '/habitacion_3indv.jpg', alt: 'Habitación triple — vista alternativa' },
  { src: '/habitacion_adorno.jpg',alt: 'Habitación — detalle decorativo' },
  { src: '/comedor.jpg',          alt: 'Comedor / desayunador' },
  { src: '/comedor_sillas.jpg',   alt: 'Desayunador — sillas' },
  { src: '/comida_comedor.jpg',   alt: 'Desayuno incluido' },
];

export default function Galeria() {
  const [lightboxIndex, setLightboxIndex] = useState<number | null>(null);

  return (
    <section id="galeria" className="pt-8 sm:pt-10 pb-16 sm:pb-20 bg-crema">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 className="section-title">Galería</h2>
        <div className="section-separator" />
        <p className="text-center text-foreground/60 mb-10 max-w-2xl mx-auto">
          Conocé nuestras instalaciones y viví la experiencia Las Heras Hotel antes de tu llegada.
        </p>

        {/* Photo grid */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          {GALLERY_IMAGES.map((img, i) => (
            <button
              key={i}
              onClick={() => setLightboxIndex(i)}
              className="group relative aspect-[4/3] rounded-lg overflow-hidden border border-white/60 shadow-sm hover:shadow-lg transition-shadow duration-300 cursor-pointer bg-crema"
            >
              <img
                src={img.src}
                alt={img.alt}
                className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
              />

              {/* Hover overlay */}
              <div className="absolute inset-0 bg-azul-marino/0 group-hover:bg-azul-marino/25 transition-colors duration-300 flex items-end p-3">
                <span className="text-white opacity-0 group-hover:opacity-100 transition-opacity duration-300 text-xs font-semibold bg-black/30 rounded px-2 py-1">
                  {img.alt}
                </span>
              </div>
            </button>
          ))}
        </div>
      </div>

      {/* Lightbox */}
      {lightboxIndex !== null && (
        <Lightbox
          images={GALLERY_IMAGES.map((img) => img.src)}
          initialIndex={lightboxIndex}
          onClose={() => setLightboxIndex(null)}
        />
      )}
    </section>
  );
}
