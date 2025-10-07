import React from "react";
import dayjs from '/imports/utils/dayjsConfig';

const MultiDaySegmentsBar = ({ segment, startDate, index }) => {
    const segmentStart = dayjs(segment.dateStart);
    const segmentEnd = dayjs(segment.dateEnd);
    const gridStart = dayjs(startDate);
    const visibleDays = 7;

    // Calcular desplazamiento inicial (en días) relativo a la grilla
    const startOffset = Math.max(0, segmentStart.diff(gridStart, "day"));
    // Calcular el fin visible dentro de la grilla
    const endOffset = Math.min(visibleDays - 1, segmentEnd.diff(gridStart, "day"));
    // Duración visible en días (mínimo 1 día)
    const durationInDays = endOffset - startOffset + 1;

    // Si está completamente fuera del rango visible, no renderizar
    if (endOffset < 0 || startOffset > visibleDays - 1) return null;

    // Calcular porcentaje de posición y ancho respecto a 7 días
    const leftPercentage = (startOffset / visibleDays) * 100;
    const widthPercentage = (durationInDays / visibleDays) * 100;

    return (
        <div
            className="bg-blue-500 rounded text-white text-xs h-4 px-2 my-1 overflow-hidden"
            style={{
                left: `${leftPercentage}%`,
                width: `${widthPercentage}%`,
                top: `${index * 12}px`,
            }}
            title={`${segment.dateStart} - ${segment.dateEnd}`}
        >
            {segment.name || `${segment.dateStart} - ${segment.dateEnd}`}
        </div>
    );
};

export default MultiDaySegmentsBar;
