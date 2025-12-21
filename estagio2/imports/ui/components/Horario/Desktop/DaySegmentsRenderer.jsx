// C:\Proyectos\intranek\imports\ui\components\Horario\Desktop\DaySegmentsRenderer.jsx
//
// Renderiza segmentos dentro de una columna (día) en la grilla de escritorio.
//
// Interpretación de campos (producción):
// - start/end: HH:mm (segmentos del día)
// - dateStart/dateEnd: ISO con fecha/hora (multi-día → se pinta con MultiDaySegmentsBar)

import React from "react";
import dayjs from "/imports/utils/dayjsConfig";

/**
 * Dibuja un bloque con posición absoluta dentro de una columna.
 * @param {{start?:string,end?:string,id?:string|number,label?:string,name?:string,type?:string}} segment
 * @param {string} color
 * @param {number} blockHeight
 * @param {string} minHour
 */
function renderSegmentBlock(segment, color, blockHeight, minHour) {
  if (!segment?.start || !segment?.end) return null;

  const base = "2024-01-01T";
  const start = dayjs(`${base}${segment.start}`);
  let end = dayjs(`${base}${segment.end}`);
  const min = dayjs(`${base}${minHour}`);

  // Guarda: si por algún motivo end < start (segmento “cruza medianoche”),
  // lo extendemos al día siguiente para evitar alturas negativas.
  if (end.isValid() && start.isValid() && end.isBefore(start)) {
    end = end.add(1, "day");
  }

  if (!start.isValid() || !end.isValid() || !min.isValid()) return null;

  const durationInHours = end.diff(start, "minute") / 60;
  const offsetFromMin = start.diff(min, "minute") / 60;

  const height = Math.max(0, durationInHours * blockHeight);
  const top = offsetFromMin * blockHeight;

  const key = segment.id ?? `${segment.start}-${segment.end}`;

  return (
    <div
      key={key}
      className="absolute left-1 right-1 rounded text-white text-xs px-2 py-1 shadow-md overflow-hidden"
      style={{ top, height, backgroundColor: color }}
      title={`${segment.start} - ${segment.end}`}
    >
      {segment.start} - {segment.end}
    </div>
  );
}

/**
 * @param {{
 *   segments?: any[],
 *   extraSegments?: any[],
 *   blockHeight: number,
 *   minHour: string
 * }} props
 */
const DaySegmentsRenderer = ({
  segments = [],
  extraSegments = [],
  blockHeight,
  minHour,
}) => {
  return (
    <div className="relative w-full h-full">
      {/* Segmentos principales del día (start/end en HH:mm) */}
      {segments.map((seg) => renderSegmentBlock(seg, "#3b82f6", blockHeight, minHour))}

      {/* Segmentos extra del día (start/end en HH:mm) */}
      {extraSegments.map((seg) => renderSegmentBlock(seg, "#f97316", blockHeight, minHour))}
    </div>
  );
};

export default DaySegmentsRenderer;
