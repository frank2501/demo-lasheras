'use client';

import { useState, useEffect } from 'react';
import { Menu, X, Ticket } from 'lucide-react';
import Link from 'next/link';
import { usePathname } from 'next/navigation';

const NAV_LINKS = [
  { href: '#inicio', label: 'Inicio' },
  { href: '/habitaciones', label: 'Habitaciones', isPage: true },
  { href: '#galeria', label: 'Galería' },
  { href: '#resenias', label: 'Reseñas' },
  { href: '#ubicacion', label: 'Ubicación' },
];

export default function Navbar() {
  const [isOpen, setIsOpen] = useState(false);
  const [scrolled, setScrolled] = useState(false);
  const pathname = usePathname();

  useEffect(() => {
    const handleScroll = () => setScrolled(window.scrollY > 20);
    window.addEventListener('scroll', handleScroll);
    return () => window.removeEventListener('scroll', handleScroll);
  }, []);

  return (
    <header
      className={`fixed top-0 left-0 right-0 z-50 transition-shadow duration-300 bg-white ${
        scrolled ? 'shadow-md' : ''
      }`}
    >
      <nav className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between h-16 sm:h-20">
        {/* Logo */}
        <Link
          href="/"
          className="flex items-center gap-2.5 font-[var(--font-display)] text-xl sm:text-2xl font-bold text-azul-marino tracking-wide"
          style={{ fontFamily: 'var(--font-display)' }}
        >
          <img src="/logo.png" alt="LH" className="h-10 sm:h-12 w-auto" />
          Las Heras Hotel
        </Link>
        
        {/* Desktop nav */}
        <ul className="hidden md:flex items-center gap-6 lg:gap-8">
          {NAV_LINKS.map((link) => (
            <li key={link.href}>
              {link.isPage ? (
                <Link
                  href={link.href}
                  className="text-sm font-semibold text-azul-marino hover:text-azul-cielo transition-colors duration-200 tracking-wide uppercase"
                >
                  {link.label}
                </Link>
              ) : (
                <a
                  href={pathname === '/' ? link.href : `/${link.href}`}
                  className="text-sm font-semibold text-azul-marino hover:text-azul-cielo transition-colors duration-200 tracking-wide uppercase"
                >
                  {link.label}
                </a>
              )}
            </li>
          ))}
          <li>
            <Link
              href="/buscar-reserva"
              className="inline-flex items-center gap-1.5 text-sm font-semibold text-azul-cielo border border-azul-cielo/40 hover:bg-azul-cielo hover:text-white rounded-lg px-4 py-1.5 transition-colors duration-200"
            >
              <Ticket size={15} />
              Mi reserva
            </Link>
          </li>
        </ul>

        {/* Mobile hamburger */}
        <button
          onClick={() => setIsOpen(!isOpen)}
          className="md:hidden text-azul-marino p-2"
          aria-label="Abrir menú"
        >
          {isOpen ? <X size={24} /> : <Menu size={24} />}
        </button>
      </nav>

      {/* Mobile menu */}
      {isOpen && (
        <div className="md:hidden bg-white border-t border-crema">
          <ul className="flex flex-col px-4 py-4 gap-1">
            {NAV_LINKS.map((link) => (
              <li key={link.href}>
                {link.isPage ? (
                  <Link
                    href={link.href}
                    onClick={() => setIsOpen(false)}
                    className="block py-3 px-2 text-sm font-semibold text-azul-marino hover:text-azul-cielo hover:bg-crema rounded-md transition-colors uppercase tracking-wide"
                  >
                    {link.label}
                  </Link>
                ) : (
                  <a
                    href={pathname === '/' ? link.href : `/${link.href}`}
                    onClick={() => setIsOpen(false)}
                    className="block py-3 px-2 text-sm font-semibold text-azul-marino hover:text-azul-cielo hover:bg-crema rounded-md transition-colors uppercase tracking-wide"
                  >
                    {link.label}
                  </a>
                )}
              </li>
            ))}
            <li>
              <Link
                href="/buscar-reserva"
                onClick={() => setIsOpen(false)}
                className="flex items-center gap-2 py-3 px-2 text-sm font-semibold text-azul-cielo hover:bg-crema rounded-md transition-colors"
              >
                <Ticket size={15} />
                Mi reserva
              </Link>
            </li>
          </ul>
        </div>
      )}
    </header>
  );
}
