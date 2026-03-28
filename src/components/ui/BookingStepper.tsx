import { Check } from 'lucide-react';

const STEPS = ['Fechas', 'Habitaciones', 'Datos', 'Reservar'];

export default function BookingStepper({ current }: { current: number }) {
  return (
    <div className="flex items-center justify-center gap-0 py-6">
      {STEPS.map((label, i) => {
        const stepNum = i + 1;
        const isDone = stepNum < current;
        const isActive = stepNum === current;

        return (
          <div key={label} className="flex items-center">
            {i > 0 && (
              <div
                className={`w-16 sm:w-24 h-0.5 transition-colors ${
                  isDone || isActive ? 'bg-azul-cielo' : 'bg-gray-200'
                }`}
              />
            )}
            <div className="flex flex-col items-center gap-1.5">
              <div
                className={`w-9 h-9 rounded-full flex items-center justify-center text-sm font-bold transition-all ${
                  isDone
                    ? 'bg-azul-cielo text-white'
                    : isActive
                    ? 'bg-azul-cielo text-white shadow-lg shadow-azul-cielo/30'
                    : 'bg-gray-100 text-gray-400 border border-gray-200'
                }`}
              >
                {isDone ? <Check size={16} /> : stepNum}
              </div>
              <span
                className={`text-xs font-medium ${
                  isDone || isActive ? 'text-azul-cielo' : 'text-gray-400'
                }`}
              >
                {label}
              </span>
            </div>
          </div>
        );
      })}
    </div>
  );
}
