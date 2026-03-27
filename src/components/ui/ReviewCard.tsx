import { Star } from 'lucide-react';

interface ReviewCardProps {
  name: string;
  rating: number;
  date: string;
  text: string;
}

export default function ReviewCard({ name, rating, date, text }: ReviewCardProps) {
  const initials = name
    .split(' ')
    .map((w) => w[0])
    .join('')
    .toUpperCase()
    .slice(0, 2);

  return (
    <div className="card p-5 flex flex-col h-full">
      {/* Header */}
      <div className="flex items-center gap-3 mb-3">
        {/* Avatar */}
        <div className="w-10 h-10 rounded-full bg-azul-cielo/15 text-azul-marino flex items-center justify-center font-bold text-sm shrink-0">
          {initials}
        </div>
        <div className="flex-1 min-w-0">
          <p className="font-semibold text-azul-marino text-sm truncate">{name}</p>
          <p className="text-xs text-foreground/40">{date}</p>
        </div>
      </div>

      {/* Stars */}
      <div className="flex items-center gap-0.5 mb-3">
        {[1, 2, 3, 4, 5].map((star) => (
          <Star
            key={star}
            size={14}
            className={star <= rating ? 'star-filled fill-dorado' : 'star-empty'}
          />
        ))}
      </div>

      {/* Text */}
      <p className="text-sm text-foreground/70 leading-relaxed flex-1">
        &ldquo;{text}&rdquo;
      </p>
    </div>
  );
}
