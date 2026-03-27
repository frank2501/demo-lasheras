export default function SkeletonCard() {
  return (
    <div className="card flex flex-col animate-pulse">
      {/* Photo skeleton */}
      <div className="h-52 sm:h-56 skeleton" />

      <div className="p-5 flex flex-col gap-3">
        {/* Title */}
        <div className="skeleton h-6 w-3/4 rounded" />

        {/* Description lines */}
        <div className="space-y-2">
          <div className="skeleton h-4 w-full rounded" />
          <div className="skeleton h-4 w-5/6 rounded" />
        </div>

        {/* Capacity */}
        <div className="flex gap-3 mt-1">
          <div className="skeleton h-4 w-20 rounded" />
          <div className="skeleton h-4 w-16 rounded" />
        </div>

        {/* Amenities */}
        <div className="flex gap-2 mt-1">
          <div className="skeleton h-6 w-16 rounded-full" />
          <div className="skeleton h-6 w-20 rounded-full" />
          <div className="skeleton h-6 w-14 rounded-full" />
        </div>

        {/* Price */}
        <div className="border-t border-crema pt-4 mt-3">
          <div className="skeleton h-7 w-28 rounded mb-2" />
          <div className="skeleton h-4 w-20 rounded mb-3" />
          <div className="skeleton h-11 w-full rounded" />
        </div>
      </div>
    </div>
  );
}
