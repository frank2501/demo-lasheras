'use client';

import { useState, useEffect, useCallback } from 'react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { getCalendarHints, type CalendarHintDay } from '@/lib/api';

/* ── Types ───────────────────────────────────────── */

interface AvailabilityCalendarProps {
  checkIn: string;
  checkOut: string;
  onCheckInChange: (date: string) => void;
  onCheckOutChange: (date: string) => void;
}

type SelectionState = 'idle' | 'checkin_selected';

/* ── Helpers ─────────────────────────────────────── */

const WEEKDAYS = ['LUN', 'MAR', 'MIÉ', 'JUE', 'VIE', 'SÁB', 'DOM'];
const MONTH_NAMES = [
  'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
  'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre',
];

function toISO(y: number, m: number, d: number): string {
  return `${y}-${String(m + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
}

function todayISO(): string {
  const d = new Date();
  return toISO(d.getFullYear(), d.getMonth(), d.getDate());
}

/* ── Calendar Component ──────────────────────────── */

export default function AvailabilityCalendar({
  checkIn,
  checkOut,
  onCheckInChange,
  onCheckOutChange,
}: AvailabilityCalendarProps) {
  const today = todayISO();
  const now = new Date();

  const [viewMonth, setViewMonth] = useState(now.getMonth());
  const [viewYear, setViewYear] = useState(now.getFullYear());
  const [hints, setHints] = useState<Record<string, CalendarHintDay>>({});
  const [selectionState, setSelectionState] = useState<SelectionState>(
    checkIn && !checkOut ? 'checkin_selected' : 'idle'
  );
  const [hoverDate, setHoverDate] = useState('');

  useEffect(() => {
    getCalendarHints(6).then((res) => {
      if (res.days) setHints(res.days);
    }).catch(() => {});
  }, []);

  const canGoBack = viewYear > now.getFullYear() || viewMonth > now.getMonth();

  const goBack = () => {
    if (!canGoBack) return;
    if (viewMonth === 0) { setViewMonth(11); setViewYear(viewYear - 1); }
    else { setViewMonth(viewMonth - 1); }
  };

  const goForward = () => {
    setViewMonth((viewMonth + 1) % 12);
    if (viewMonth === 11) setViewYear(viewYear + 1);
  };

  const secondMonth = (viewMonth + 1) % 12;
  const secondYear = viewMonth === 11 ? viewYear + 1 : viewYear;

  const handleDayClick = useCallback((dateStr: string) => {
    if (dateStr < today) return;
    const hint = hints[dateStr];
    if (hint && hint.s === 'full') return;

    if (selectionState === 'idle' || (selectionState === 'checkin_selected' && dateStr <= checkIn)) {
      onCheckInChange(dateStr);
      onCheckOutChange('');
      setSelectionState('checkin_selected');
      setHoverDate('');
    } else {
      onCheckOutChange(dateStr);
      setSelectionState('idle');
      setHoverDate('');
    }
  }, [selectionState, checkIn, hints, today, onCheckInChange, onCheckOutChange]);

  const handleDayHover = useCallback((dateStr: string) => {
    if (selectionState === 'checkin_selected' && dateStr > checkIn) {
      setHoverDate(dateStr);
    }
  }, [selectionState, checkIn]);

  const handleMouseLeave = useCallback(() => {
    setHoverDate('');
  }, []);

  const clearSelection = () => {
    onCheckInChange('');
    onCheckOutChange('');
    setSelectionState('idle');
    setHoverDate('');
  };

  // The effective checkout for range display: real checkOut or hovered date
  const effectiveCheckOut = checkOut || hoverDate;

  return (
    <div className="bg-white rounded-xl shadow-lg border border-gray-100 p-3 sm:p-4" onMouseLeave={handleMouseLeave}>
      <div className="grid grid-cols-1 md:grid-cols-2 gap-3 md:gap-6">
        <MonthGrid
          year={viewYear}
          month={viewMonth}
          today={today}
          hints={hints}
          checkIn={checkIn}
          checkOut={checkOut}
          effectiveCheckOut={effectiveCheckOut}
          isHovering={!!hoverDate && !checkOut}
          onDayClick={handleDayClick}
          onDayHover={handleDayHover}
          canGoBack={canGoBack}
          onGoBack={goBack}
          onGoForward={null}
        />
        <MonthGrid
          year={secondYear}
          month={secondMonth}
          today={today}
          hints={hints}
          checkIn={checkIn}
          checkOut={checkOut}
          effectiveCheckOut={effectiveCheckOut}
          isHovering={!!hoverDate && !checkOut}
          onDayClick={handleDayClick}
          onDayHover={handleDayHover}
          canGoBack={false}
          onGoBack={null}
          onGoForward={goForward}
        />
      </div>

      {/* Legend + clear */}
      <div className="flex flex-wrap items-center justify-between mt-3 pt-2.5 border-t border-gray-100">
        <div className="flex items-center gap-4 text-[10px]">
          <span className="flex items-center gap-1"><span className="w-2 h-2 rounded-full bg-red-400" />Ocupado</span>
          <span className="flex items-center gap-1"><span className="w-2 h-2 rounded-full bg-amber-400" />Últimos lugares</span>
          <span className="flex items-center gap-1"><span className="w-2 h-2 rounded-full bg-emerald-400" />Oferta</span>
        </div>
        {(checkIn || checkOut) && (
          <button onClick={clearSelection} className="text-[10px] text-red-500 font-semibold uppercase tracking-wider hover:text-red-600">
            Borrar selección
          </button>
        )}
      </div>
    </div>
  );
}

/* ── Single Month Grid ───────────────────────────── */

function MonthGrid({
  year, month, today, hints, checkIn, checkOut, effectiveCheckOut, isHovering,
  onDayClick, onDayHover, canGoBack, onGoBack, onGoForward,
}: {
  year: number;
  month: number;
  today: string;
  hints: Record<string, CalendarHintDay>;
  checkIn: string;
  checkOut: string;
  effectiveCheckOut: string;
  isHovering: boolean;
  onDayClick: (date: string) => void;
  onDayHover: (date: string) => void;
  canGoBack: boolean;
  onGoBack: (() => void) | null;
  onGoForward: (() => void) | null;
}) {
  const firstDay = new Date(year, month, 1);
  let startDay = firstDay.getDay() - 1;
  if (startDay < 0) startDay = 6;
  const daysInMonth = new Date(year, month + 1, 0).getDate();

  const cells: (number | null)[] = [];
  for (let i = 0; i < startDay; i++) cells.push(null);
  for (let d = 1; d <= daysInMonth; d++) cells.push(d);

  return (
    <div>
      {/* Month header */}
      <div className="flex items-center justify-between mb-4">
        {onGoBack ? (
          <button
            onClick={onGoBack}
            disabled={!canGoBack}
            className={`p-1 rounded-full transition-colors ${
              canGoBack ? 'hover:bg-gray-100 text-azul-marino' : 'text-gray-200 cursor-not-allowed'
            }`}
          >
            <ChevronLeft size={20} />
          </button>
        ) : (
          <div className="w-7" />
        )}
        <h3
          className="text-base font-bold text-azul-marino"
          style={{ fontFamily: 'var(--font-display)' }}
        >
          {MONTH_NAMES[month]} {year}
        </h3>
        {onGoForward ? (
          <button
            onClick={onGoForward}
            className="p-1 rounded-full hover:bg-gray-100 text-azul-marino transition-colors"
          >
            <ChevronRight size={20} />
          </button>
        ) : (
          <div className="w-7" />
        )}
      </div>

      {/* Weekday headers */}
      <div className="grid grid-cols-7">
        {WEEKDAYS.map((wd) => (
          <div key={wd} className="text-center text-[9px] font-semibold text-gray-400 uppercase tracking-wider py-0.5">
            {wd}
          </div>
        ))}
      </div>

      {/* Day cells */}
      <div className="grid grid-cols-7">
        {cells.map((day, i) => {
          if (day === null) return <div key={`e-${i}`} className="p-1" />;

          const dateStr = toISO(year, month, day);
          const isPast = dateStr < today;
          const isToday = dateStr === today;
          const isCheckIn = dateStr === checkIn;
          const isCheckOut = dateStr === checkOut;

          // Confirmed range
          const isInRange = !!checkIn && !!checkOut && dateStr > checkIn && dateStr < checkOut;
          // Hover preview range (only when hovering and no checkOut confirmed)
          const isInHoverRange = isHovering && !!checkIn && !checkOut && !!effectiveCheckOut
            && dateStr > checkIn && dateStr <= effectiveCheckOut;
          const isHoverEnd = isHovering && dateStr === effectiveCheckOut && !checkOut;

          const hint = hints[dateStr];
          const isFull = hint?.s === 'full';
          const isLow = hint?.s === 'low';
          const hasPromo = !!hint?.p;

          let cellClass = 'relative flex items-center justify-center h-9 text-xs rounded-md transition-all ';
          let dotColor = '';

          if (isCheckIn || isCheckOut) {
            cellClass += 'bg-azul-cielo text-white font-bold cursor-pointer ';
          } else if (isHoverEnd) {
            cellClass += 'bg-azul-cielo/60 text-white font-semibold cursor-pointer ';
          } else if (isInRange) {
            cellClass += 'bg-azul-cielo/15 text-azul-marino cursor-pointer ';
          } else if (isInHoverRange) {
            cellClass += 'bg-azul-cielo/10 text-azul-marino cursor-pointer ';
          } else if (isPast) {
            cellClass += 'text-gray-200 cursor-not-allowed ';
          } else if (isFull) {
            cellClass += 'text-gray-300 cursor-not-allowed ';
            dotColor = 'bg-red-400';
          } else if (isLow) {
            cellClass += 'text-azul-marino hover:bg-gray-50 cursor-pointer ';
            dotColor = 'bg-amber-400';
          } else if (hasPromo) {
            cellClass += 'text-azul-marino hover:bg-gray-50 cursor-pointer ';
            dotColor = 'bg-emerald-400';
          } else {
            cellClass += 'text-azul-marino hover:bg-gray-50 cursor-pointer ';
          }

          return (
            <button
              key={dateStr}
              type="button"
              onClick={() => !isPast && !isFull && onDayClick(dateStr)}
              onMouseEnter={() => !isPast && !isFull && onDayHover(dateStr)}
              disabled={isPast || isFull}
              className={cellClass}
            >
              {day}
              {isToday && !isCheckIn && !isCheckOut && (
                <span className="absolute bottom-1 left-1/2 -translate-x-1/2 w-1 h-1 rounded-full bg-azul-cielo" />
              )}
              {dotColor && !isCheckIn && !isCheckOut && !isInRange && !isInHoverRange && (
                <span className={`absolute bottom-1 left-1/2 -translate-x-1/2 w-1.5 h-1.5 rounded-full ${dotColor}`} />
              )}
            </button>
          );
        })}
      </div>
    </div>
  );
}
