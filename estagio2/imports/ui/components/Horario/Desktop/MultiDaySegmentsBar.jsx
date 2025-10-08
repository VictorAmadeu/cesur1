import React from "react";
import dayjs from '/imports/utils/dayjsConfig';

/**
 * Barra para segmentos que pueden abarcar varios días dentro de una semana visible.
 * Props:
 *  - segment:    objeto con { dateStart, dateEnd, name, ... }
 *  - startDate:  fecha de inicio de la grilla (YYYY-MM-DD)
 *  - index:      índice de fila para apilar múltiples barras (controla el "top")
 *
 * Nota: siguiendo la Opción B, este componente **no recibe endDate** como prop externa.
 *       Si alguna vez se necesitara, se añadirá aquí y en quien lo invoque.
 */
const MultiDaySegmentsBar = ({ segment, startDate, index }) => {
    const segmentStart = dayjs(segment.dateStart);
    const segmentEnd = dayjs(segment.dateEnd);
    const gridStart = dayjs(startDate);
    const visibleDays = 7;

    // Desplazamiento inicial (en días) relativo al inicio de la grilla
    const startOffset = Math.max(0, segmentStart.diff(gridStart, "day"));

    // Fin visible dentro de la grilla
    const endOffset = Math.min(visibleDays - 1, segmentEnd.diff(gridStart, "day"));

    // Duración visible en días (mínimo 1)
    const durationInDays = endOffset - startOffset + 1;

    // Si está completamente fuera del rango visible, no renderizar
    if (endOffset < 0 || startOffset > visibleDays - 1) return null;

    // Porcentajes de posición/ancho respecto a 7 días
    const leftPercentage = (startOffset / visibleDays) * 100;
    const widthPercentage = (durationInDays / visibleDays) * 100;

    return (
        <div
            className="bg-blue-500 rounded text-white text-xs h-4 px-2 my-1 overflow-hidden"
            style={{
                left: `${leftPercentage}%`,
                width: `${widthPercentage}%`,
                top: `${index * 12}px`,  // apila barras
                // IMPORTANTE: el contenedor padre debe tener position: relative,
                // y este div se posicionará con absolute si así se define en el padre.
            }}
            title={`${segment.dateStart} - ${segment.dateEnd}`}
        >
            {segment.name || `${segment.dateStart} - ${segment.dateEnd}`}
        </div>
    );
};

export default MultiDaySegmentsBar;
