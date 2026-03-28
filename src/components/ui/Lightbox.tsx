'use client';

import { useState, useEffect, useCallback } from 'react';
import { X, ChevronLeft, ChevronRight } from 'lucide-react';

interface LightboxProps {
  images: string[];
  initialIndex: number;
  onClose: () => void;
}

export default function Lightbox({ images, initialIndex, onClose }: LightboxProps) {
  const [index, setIndex] = useState(initialIndex);

  const prev = useCallback(() => {
    setIndex((i) => (i === 0 ? images.length - 1 : i - 1));
  }, [images.length]);

  const next = useCallback(() => {
    setIndex((i) => (i === images.length - 1 ? 0 : i + 1));
  }, [images.length]);

  useEffect(() => {
    const handleKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose();
      if (e.key === 'ArrowLeft') prev();
      if (e.key === 'ArrowRight') next();
    };
    document.addEventListener('keydown', handleKey);
    document.body.style.overflow = 'hidden';
    return () => {
      document.removeEventListener('keydown', handleKey);
      document.body.style.overflow = '';
    };
  }, [onClose, prev, next]);

  return (
    <div
      className="fixed inset-0 z-[100] bg-black/85 flex items-center justify-center"
      onClick={onClose}
    >
      {/* Close button */}
      <button
        onClick={onClose}
        className="absolute top-4 right-4 text-white/70 hover:text-white p-2 z-10"
        aria-label="Cerrar"
      >
        <X size={28} />
      </button>

      {/* Prev button */}
      <button
        onClick={(e) => {
          e.stopPropagation();
          prev();
        }}
        className="absolute left-4 text-white/70 hover:text-white p-2 z-10"
        aria-label="Anterior"
      >
        <ChevronLeft size={36} />
      </button>

      {/* Image */}
      <div
        className="max-w-[90vw] max-h-[85vh] relative"
        onClick={(e) => e.stopPropagation()}
      >
        <img
          src={images[index]}
          alt={`Foto ${index + 1}`}
          className="max-w-full max-h-[85vh] object-contain rounded-lg shadow-2xl"
        />
        <p className="text-white/50 text-sm text-center mt-3">
          {index + 1} / {images.length}
        </p>
      </div>

      {/* Next button */}
      <button
        onClick={(e) => {
          e.stopPropagation();
          next();
        }}
        className="absolute right-4 text-white/70 hover:text-white p-2 z-10"
        aria-label="Siguiente"
      >
        <ChevronRight size={36} />
      </button>
    </div>
  );
}
