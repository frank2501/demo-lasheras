import { MapPin, Phone, Mail, Clock, Navigation, Waves, Train, Plane } from 'lucide-react';

const NEARBY_PLACES = [
  { name: 'Bristol Beach', distance: '950 m', type: 'beach' },
  { name: 'Centro Comercial Güemes', distance: '400 m', type: 'place' },
  { name: 'Calle peatonal San Martín', distance: '1,2 km', type: 'place' },
  { name: 'Villa Victoria', distance: '1,1 km', type: 'place' },
  { name: 'Playas Centro', distance: '1,4 km', type: 'beach' },
  { name: 'Torreón del Monje', distance: '1,5 km', type: 'place' },
  { name: 'Catedral Mar del Plata', distance: '2,1 km', type: 'place' },
  { name: 'Museo MAR', distance: '4,8 km', type: 'place' },
];

const TRANSPORT = [
  { name: 'Terminal de Ómnibus', distance: '3,8 km', icon: Train },
  { name: 'Estación de trenes', distance: '4,1 km', icon: Train },
  { name: 'Aeropuerto Astor Piazzolla', distance: '9 km', icon: Plane },
];

export default function Ubicacion() {
  return (
    <section id="ubicacion" className="py-16 sm:py-20 bg-crema">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 className="section-title">¿Dónde estamos?</h2>
        <div className="section-separator" />
        <p className="text-center text-foreground/60 mb-10 max-w-2xl mx-auto">
          En pleno centro de Mar del Plata, a 600 metros de la playa. A los huéspedes les encanta caminar por el barrio.
        </p>

        <div className="grid grid-cols-1 lg:grid-cols-5 gap-8 mb-12 items-stretch">
          {/* Map */}
          <div className="lg:col-span-3 flex flex-col">
            <div className="rounded-lg overflow-hidden border border-white/60 shadow-sm flex-1 min-h-[350px]">
              <iframe
                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3143.5!2d-57.5534!3d-38.0055!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x9584dc440d35c4a1%3A0x1e0c7e3e6b9e7c0a!2sLas%20Heras%202849%2C%20B7602%20Mar%20del%20Plata%2C%20Provincia%20de%20Buenos%20Aires!5e0!3m2!1ses-419!2sar!4v1700000000000!5m2!1ses-419!2sar"
                width="100%"
                height="100%"
                style={{ border: 0 }}
                allowFullScreen
                loading="lazy"
                referrerPolicy="no-referrer-when-downgrade"
                title="Ubicación de Las Heras Hotel en Mar del Plata"
              />
            </div>
            <a
              href="https://maps.app.goo.gl/LLR7QeN68W2GEuUt5"
              target="_blank"
              rel="noopener noreferrer"
              className="inline-flex items-center gap-2 text-sm text-azul-cielo hover:text-azul-marino mt-3 transition-colors"
            >
              <MapPin size={14} />
              Ver en Google Maps
            </a>
          </div>

          {/* Contact info */}
          <div className="lg:col-span-2 flex flex-col">
            <div className="card p-6 sm:p-8 h-full">
              <h3
                className="text-xl font-bold text-azul-marino mb-6"
                style={{ fontFamily: 'var(--font-display)' }}
              >
                Datos de contacto
              </h3>

              <ul className="space-y-5">
                <li className="flex items-start gap-4">
                  <div className="w-10 h-10 rounded-full bg-azul-cielo/10 flex items-center justify-center shrink-0">
                    <MapPin size={18} className="text-azul-cielo" />
                  </div>
                  <div>
                    <p className="font-semibold text-sm text-azul-marino">Dirección</p>
                    <p className="text-sm text-foreground/70">
                      CSA, Las Heras 2849
                      <br />
                      B7602 Mar del Plata, Provincia de Buenos Aires
                    </p>
                  </div>
                </li>

                <li className="flex items-start gap-4">
                  <div className="w-10 h-10 rounded-full bg-azul-cielo/10 flex items-center justify-center shrink-0">
                    <Phone size={18} className="text-azul-cielo" />
                  </div>
                  <div>
                    <p className="font-semibold text-sm text-azul-marino">Teléfono</p>
                    <a href="tel:02234937841" className="text-sm text-foreground/70 hover:text-azul-cielo transition-colors">
                      0223 493-7841
                    </a>
                  </div>
                </li>

                <li className="flex items-start gap-4">
                  <div className="w-10 h-10 rounded-full bg-azul-cielo/10 flex items-center justify-center shrink-0">
                    <Mail size={18} className="text-azul-cielo" />
                  </div>
                  <div>
                    <p className="font-semibold text-sm text-azul-marino">Email</p>
                    <p className="text-sm text-foreground/70">reservas@lasherashotel.com</p>
                  </div>
                </li>

                <li className="flex items-start gap-4">
                  <div className="w-10 h-10 rounded-full bg-azul-cielo/10 flex items-center justify-center shrink-0">
                    <Clock size={18} className="text-azul-cielo" />
                  </div>
                  <div>
                    <p className="font-semibold text-sm text-azul-marino">Horarios</p>
                    <p className="text-sm text-foreground/70">
                      Check-in: a partir de las 14:00
                      <br />
                      Check-out: hasta las 10:00
                    </p>
                  </div>
                </li>
              </ul>
            </div>
          </div>
        </div>

        {/* Nearby places */}
        <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
          {/* Attractions & beaches */}
          <div className="bg-white rounded-lg p-6 border border-white/80 shadow-sm">
            <h3
              className="text-lg font-bold text-azul-marino mb-5 flex items-center gap-2"
              style={{ fontFamily: 'var(--font-display)' }}
            >
              <Navigation size={18} className="text-azul-cielo" />
              Qué hay cerca
            </h3>
            <ul className="space-y-3">
              {NEARBY_PLACES.map((place) => (
                <li key={place.name} className="flex items-center justify-between text-sm">
                  <span className="flex items-center gap-2 text-foreground/70">
                    {place.type === 'beach' ? (
                      <Waves size={14} className="text-azul-cielo shrink-0" />
                    ) : (
                      <MapPin size={14} className="text-dorado shrink-0" />
                    )}
                    {place.name}
                  </span>
                  <span className="text-foreground/40 text-xs font-medium ml-3 shrink-0">{place.distance}</span>
                </li>
              ))}
            </ul>
          </div>

          {/* Transport + restaurants */}
          <div className="bg-white rounded-lg p-6 border border-white/80 shadow-sm">
            <h3
              className="text-lg font-bold text-azul-marino mb-5 flex items-center gap-2"
              style={{ fontFamily: 'var(--font-display)' }}
            >
              <Train size={18} className="text-azul-cielo" />
              Transporte
            </h3>
            <ul className="space-y-3 mb-6">
              {TRANSPORT.map((t) => {
                const Icon = t.icon;
                return (
                  <li key={t.name} className="flex items-center justify-between text-sm">
                    <span className="flex items-center gap-2 text-foreground/70">
                      <Icon size={14} className="text-azul-cielo shrink-0" />
                      {t.name}
                    </span>
                    <span className="text-foreground/40 text-xs font-medium ml-3 shrink-0">{t.distance}</span>
                  </li>
                );
              })}
            </ul>

            <h4 className="font-bold text-sm text-azul-marino mb-3 uppercase tracking-wide">
              Restaurantes cerca
            </h4>
            <ul className="space-y-2">
              <li className="flex items-center justify-between text-sm text-foreground/70">
                <span>Le Pain Quotidien</span>
                <span className="text-foreground/40 text-xs">100 m</span>
              </li>
              <li className="flex items-center justify-between text-sm text-foreground/70">
                <span>Valentino</span>
                <span className="text-foreground/40 text-xs">100 m</span>
              </li>
              <li className="flex items-center justify-between text-sm text-foreground/70">
                <span>La Luna de Tiziano</span>
                <span className="text-foreground/40 text-xs">200 m</span>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </section>
  );
}
