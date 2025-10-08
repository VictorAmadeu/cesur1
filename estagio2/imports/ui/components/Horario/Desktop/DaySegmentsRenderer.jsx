// (Opcional pero inofensivo) Si tu proyecto no usa el runtime JSX automático,
// mantener este import evita warnings en algunos entornos.
import React from "react";

// ✅ Corrección principal: importar dayjs desde la config compartida del proyecto.
import dayjs from "/imports/utils/dayjsConfig";

/**
 * Renderiza los segmentos de un día dentro de la grilla de horarios.
 * - daySegments: segmentos "principales" (p.ej. horario laboral)
 * - segments:    segmentos "extra" (p.ej. pausas, incidencias)
 * - blockHeight: alto (px) que representa 1 hora en la grilla
 * - minHour:     hora mínima mostrada (formato "HH:mm")
 */
const DaySegmentsRenderer = ({
  daySegments = [],
  segments = [],
  blockHeight,
  minHour,
}) => {
  /**
   * Dibuja un segmento en posición absoluta, calculando:
   * - height: duración (horas) * blockHeight
   * - top:    desplazamiento desde minHour * blockHeight
   */
  const renderSegment = (segment, color) => {
    // Pequeña guarda defensiva para evitar errores si llegan datos incompletos.
    if (!segment?.start || !segment?.end) return null;

    const base = "2024-01-01T";
    const start = dayjs(`${base}${segment.start}`);
    const end = dayjs(`${base}${segment.end}`);
    const min = dayjs(`${base}${minHour}`);

    const durationInHours = end.diff(start, "minute") / 60;
    const offsetFromMin = start.diff(min, "minute") / 60;

    const height = durationInHours * blockHeight;
    const top = offsetFromMin * blockHeight;

    return (
      <div
        // Si no hay id, usamos un fallback estable con start-end para evitar warnings de React.
        key={segment.id ?? `${segment.start}-${segment.end}`}
        className="absolute left-1 right-1 rounded text-white text-xs px-2 py-1 shadow-md overflow-hidden"
        style={{ top, height, backgroundColor: color }}
        title={`${segment.start} - ${segment.end}`}
      >
        {segment.start} - {segment.end}
      </div>
    );
  };

  return (
    <div className="relative w-full h-full">
      {/* daySegments (ej. horario laboral) en azul */}
      {daySegments.map((seg) => renderSegment(seg, "#3b82f6"))}

      {/* segments extra (ej. pausas/incidencias) en naranja */}
      {segments.map((seg) => renderSegment(seg, "#f97316"))}
    </div>
  );
};

// ✅ Corrección principal: export por defecto para que el import funcione sin errores.
export default DaySegmentsRenderer;
