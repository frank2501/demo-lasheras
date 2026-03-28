import { MapPin, Phone, Mail } from 'lucide-react';
import Link from 'next/link';

const NAV_LINKS = [
  { href: '/#inicio', label: 'Inicio' },
  { href: '/habitaciones', label: 'Habitaciones' },
  { href: '/#galeria', label: 'Galería' },
  { href: '/#resenias', label: 'Reseñas' },
  { href: '/#ubicacion', label: 'Ubicación' },
];

export default function Footer() {
  return (
    <footer className="bg-azul-marino text-white/90">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div className="grid grid-cols-1 md:grid-cols-3 gap-10">
          {/* Logo & tagline */}
          <div>
            <h3
              className="text-2xl font-bold text-white mb-3"
              style={{ fontFamily: 'var(--font-display)' }}
            >
              Las Heras Hotel
            </h3>
            <p className="text-sm text-white/70 leading-relaxed">
              A pasos del mar, en el corazón de Mar del Plata.
              <br />
              Hospitalidad argentina desde siempre.
            </p>
          </div>

          {/* Navigation */}
          <div>
            <h4 className="font-bold text-white mb-4 uppercase text-sm tracking-wider">
              Navegación
            </h4>
            <ul className="space-y-2">
              {NAV_LINKS.map((link) => (
                <li key={link.href}>
                  <Link
                    href={link.href}
                    className="text-sm text-white/70 hover:text-dorado transition-colors duration-200"
                  >
                    {link.label}
                  </Link>
                </li>
              ))}
            </ul>
          </div>

          {/* Contact info */}
          <div>
            <h4 className="font-bold text-white mb-4 uppercase text-sm tracking-wider">
              Contacto
            </h4>
            <ul className="space-y-3">
              <li className="flex items-start gap-3 text-sm text-white/70">
                <MapPin size={16} className="mt-0.5 text-dorado shrink-0" />
                <span>CSA, Las Heras 2849, B7602 Mar del Plata</span>
              </li>
              <li className="flex items-center gap-3 text-sm text-white/70">
                <Phone size={16} className="text-dorado shrink-0" />
                <span>0223 493-7841</span>
              </li>
              <li className="flex items-center gap-3 text-sm text-white/70">
                <Mail size={16} className="text-dorado shrink-0" />
                <span>reservas@lasherashotel.com</span>
              </li>
            </ul>
          </div>
        </div>

        {/* Bottom bar */}
        <div className="mt-10 pt-6 border-t border-white/15 flex flex-col sm:flex-row items-center justify-between gap-3">
          <p className="text-xs text-white/50">
            © 2026 Las Heras Hotel — Todos los derechos reservados
          </p>
          <p className="text-xs text-white/50">
            Sitio desarrollado por{' '}
            <a
              href="https://artechia.com"
              target="_blank"
              rel="noopener noreferrer"
              className="text-white/80 hover:text-white hover:underline transition-colors"
            >
              Artechia
            </a>
          </p>
        </div>
      </div>
    </footer>
  );
}
