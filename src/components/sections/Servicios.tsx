import {
  Wifi,
  Clock,
  Users,
  Ban,
  Bath,
  ShowerHead,
  Tv,
  Thermometer,
  Phone,
  Wind,
  Luggage,
  Info,
  AlarmClock,
  Zap,
  MonitorPlay,
  BedDouble,
  Bell,
  Plane,
  Shirt,
  ShieldCheck,
  Flag,
  Coffee,
  Gamepad2,
} from 'lucide-react';

/* ── Data ─────────────────────────────────────────────── */

const HABITACION_ITEMS = [
  { icon: Bath,        label: 'Baño privado' },
  { icon: ShowerHead,  label: 'Ducha' },
  { icon: Wind,        label: 'Secador de pelo' },
  { icon: Tv,          label: 'TV por cable' },
  { icon: Phone,       label: 'Teléfono' },
  { icon: Thermometer, label: 'Calefacción' },
  { icon: Wind,        label: 'Ventilador' },
  { icon: Users,       label: 'Habitaciones familiares' },
  { icon: Ban,         label: 'No fumadores' },
];

const HOTEL_ITEMS = [
  { icon: ShowerHead,  label: 'Limpieza diaria' },
  { icon: Clock,       label: 'Recepción 24h' },
  { icon: Luggage,     label: 'Guardaequipaje' },
  { icon: Info,        label: 'Información turística' },
  { icon: Wifi,        label: 'Wifi gratis' },
  { icon: AlarmClock,  label: 'Despertador' },
  { icon: Plane,       label: 'Traslado (con cargo)' },
  { icon: Shirt,       label: 'Planchado' },
  { icon: Gamepad2,    label: 'Sala TV & Juegos' },
];

/* ── Component ────────────────────────────────────────── */

export default function Servicios() {
  return (
    <section className="pt-16 sm:pt-24 pb-8 sm:pb-10 bg-crema">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-16">

          {/* ─── Left column: About + feature cards ─── */}
          <div className="lg:col-span-4">
            <h2
              className="text-3xl font-bold text-azul-marino mb-3"
              style={{ fontFamily: 'var(--font-display)' }}
            >
              Sobre el Hotel
            </h2>
            <div className="w-10 h-[3px] bg-azul-cielo mb-6" />

            <p className="text-foreground/70 leading-relaxed mb-8">
              Ubicado estratégicamente a solo <strong>600 metros de la playa</strong> y a{' '}
              <strong>400 metros del centro comercial Güemes</strong>. La calle peatonal San
              Martín se encuentra a solo 15 minutos de caminata relajada.
            </p>

            {/* Feature cards */}
            <div className="space-y-5">
              {/* Desayuno */}
              <div className="bg-dorado/10 rounded-xl p-5">
                <h3
                  className="text-lg font-bold text-dorado mb-2"
                  style={{ fontFamily: 'var(--font-display)' }}
                >
                  Desayuno Artesanal
                </h3>
                <p className="text-sm text-foreground/65 leading-relaxed">
                  Empezá el día con lo mejor de nuestra cocina: yogurt casero, cereales, jugos
                  naturales exprimidos, variedad de quesos seleccionados y nuestros icónicos
                  croissants y medialunas recién horneadas.
                </p>
              </div>

              {/* Ambiente Familiar */}
              <div className="bg-azul-cielo/10 rounded-xl p-5">
                <h3
                  className="text-lg font-bold text-azul-cielo mb-2"
                  style={{ fontFamily: 'var(--font-display)' }}
                >
                  Ambiente Familiar
                </h3>
                <p className="text-sm text-foreground/65 leading-relaxed">
                  Contamos con amplias <strong>habitaciones familiares</strong> conectadas y un
                  sector de juegos para que los más chicos se sientan como en casa.
                </p>
              </div>
            </div>
          </div>

          {/* ─── Right column: Service panels ─── */}
          <div className="lg:col-span-8 flex flex-col justify-start gap-20">

            {/* En tu Habitación */}
            <div>
              <div className="flex items-center gap-3 mb-1">
                <BedDouble size={24} className="text-dorado shrink-0" />
                <h3
                  className="text-2xl font-bold text-azul-marino"
                  style={{ fontFamily: 'var(--font-display)' }}
                >
                  En tu Habitación
                </h3>
              </div>
              <p className="text-sm text-foreground/55 mb-6 pl-9">
                Equipamiento para tu descanso y el de tu familia.
              </p>

              <div className="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-4">
                {HABITACION_ITEMS.map(({ icon: Icon, label }) => (
                  <div key={label} className="flex items-center gap-2.5">
                    <Icon size={16} className="text-dorado/70 shrink-0" />
                    <span className="text-sm text-foreground/70">{label}</span>
                  </div>
                ))}
              </div>
            </div>

            {/* Servicios del Hotel */}
            <div>
              <div className="flex items-center gap-3 mb-1">
                <Bell size={24} className="text-dorado shrink-0" />
                <h3
                  className="text-2xl font-bold text-azul-marino"
                  style={{ fontFamily: 'var(--font-display)' }}
                >
                  Servicios del Hotel
                </h3>
              </div>
              <p className="text-sm text-foreground/55 mb-6 pl-9">
                Atención cálida y personalizada las 24 horas.
              </p>

              <div className="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-4">
                {HOTEL_ITEMS.map(({ icon: Icon, label }) => (
                  <div key={label} className="flex items-center gap-2.5">
                    <Icon size={16} className="text-dorado/70 shrink-0" />
                    <span className="text-sm text-foreground/70">{label}</span>
                  </div>
                ))}
              </div>
            </div>

            {/* Security & policies note */}
            <div className="flex flex-wrap items-start gap-x-6 gap-y-2 text-xs text-foreground/50 pl-0">
              <span className="flex items-center gap-1.5">
                <ShieldCheck size={14} className="text-dorado" />
                Extintores y acceso con llave
              </span>
              <span className="flex items-center gap-1.5">
                <Ban size={14} className="text-dorado" />
                Prohibido fumar en todo el establecimiento
              </span>
              <span className="flex items-center gap-1.5">
                <Flag size={14} className="text-dorado" />
                Campo de golf a menos de 3 km
              </span>
            </div>
          </div>
        </div>

      </div>
    </section>
  );
}
