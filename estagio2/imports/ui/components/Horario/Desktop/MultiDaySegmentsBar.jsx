// C:\Proyectos\intranek\imports\ui\components\Horario\Desktop\MultiDaySegmentsBar.jsx
//
// Barra superior para segmentos que cruzan días (multi-día).
//
// Interpretación de campos (producción):
// - dateStart/dateEnd: ISO con fecha/hora → define el tramo visible en la semana.

import React from "react";
import dayjs from "/imports/utils/dayjsConfig";

/**
 * Props:
 *  - segment:    { dateStart, dateEnd, name?, id? }
 *  - startDate:  YYYY-MM-DD (inicio de la semana visible)
 *  - index:      para apilar barras (top)
 */
const MultiDaySegmentsBar = ({ segment, startDate, index }) => {
  const segmentStart = dayjs(segment?.dateStart);
  const segmentEnd = dayjs(segment?.dateEnd);
  const gridStart = dayjs(startDate);

  if (!segmentStart.isValid() || !segmentEnd.isValid() || !gridStart.isValid()) return null;

  const visibleDays = 7;

  // Offset desde el inicio de la grilla (0..6)
  const startOffset = Math.max(0, segmentStart.diff(gridStart, "day"));
  const endOffset = Math.min(visibleDays - 1, segmentEnd.diff(gridStart, "day"));

  // Fuera de rango visible
  if (endOffset < 0 || startOffset > visibleDays - 1) return null;

  const durationInDays = endOffset - startOffset + 1;
  const leftPercentage = (startOffset / visibleDays) * 100;
  const widthPercentage = (durationInDays / visibleDays) * 100;

  return (
    <div
      className="absolute bg-blue-500 rounded text-white text-xs h-4 px-2 overflow-hidden"
      style={{
        left: `${leftPercentage}%`,
        width: `${widthPercentage}%`,
        top: `${index * 12}px`,
      }}
      title={`${segment?.dateStart} - ${segment?.dateEnd}`}
    >
      {segment?.name || `${segment?.dateStart} - ${segment?.dateEnd}`}
    </div>
  );
};

export default MultiDaySegmentsBar;
