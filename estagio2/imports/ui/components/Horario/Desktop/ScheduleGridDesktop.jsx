// C:\Proyectos\intranek\imports\ui\components\Horario\Desktop\ScheduleGridDesktop.jsx
//
// Grilla de escritorio reutilizada (Paso 3.3).
//
// Interpretación de campos (producción):
// - scheduleByDate[date].segments: segmentos del día (start/end en HH:mm)
// - scheduleByDate[date].extraSegments: extras del día (start/end en HH:mm)
// - extraSegments.segments: extras multi-día (dateStart/dateEnd en ISO)
//
// Nota importante (producción):
// - Se elimina dependencia de `react-icons` para evitar fallos por módulo inexistente.
// - En su lugar, se usa un SVG inline (cero dependencias).

import React, { useEffect, useState } from "react";
import dayjs from "/imports/utils/dayjsConfig";
import { useDate } from "../../../../provider/date";

import MultiDaySegmentsBar from "./MultiDaySegmentsBar";
import DaySegmentsRenderer from "./DaySegmentsRenderer";

const ScheduleGridDesktop = ({ startDate, endDate, scheduleByDate, extraSegments }) => {
  const BLOCK_HEIGHT = 60;
  const timeSlots = Array.from({ length: 24 }, (_, i) => `${String(i).padStart(2, "0")}:00`);
  const totalHeight = timeSlots.length * BLOCK_HEIGHT;

  const { setDate } = useDate();
  const [currentSlot, setCurrentSlot] = useState(null);

  // Línea de hora actual (solo UI)
  useEffect(() => {
    const updateTime = () => {
      const now = dayjs();
      const hour = now.hour().toString().padStart(2, "0");
      const minute = now.minute();
      const slot = `${hour}:00`;
      setCurrentSlot({ id: slot, minute });
    };

    updateTime();
    const interval = setInterval(updateTime, 60 * 1000);
    return () => clearInterval(interval);
  }, []);

  // 7 días visibles (lunes-domingo según startDate que recibe Horario/index.jsx)
  const days = Array.from({ length: 7 }).map((_, i) => {
    const d = dayjs(startDate).add(i, "day");
    return {
      date: d.format("YYYY-MM-DD"),
      dayName: d.format("dddd"),
      pretty: d.format("DD/MM"),
      d,
    };
  });

  const extraList = Array.isArray(extraSegments && extraSegments.segments)
    ? extraSegments.segments
    : [];

  // Solo los que cruzan días (multi-día)
  const multiDaySegments = extraList.filter((s) => {
    const ds = dayjs(s && s.dateStart);
    const de = dayjs(s && s.dateEnd);
    return ds.isValid() && de.isValid() && de.diff(ds, "day") > 0;
  });

  return (
    <div className="w-full mb-4 overflow-hidden border-t border-gray-500">
      {/* Fila 1: Botón Hoy + Cabecera de días */}
      <div
        className="grid w-full h-[60px] border-b border-gray-500 bg-white"
        style={{ gridTemplateColumns: "80px repeat(7, 1fr)" }}
      >
        {/* Columna 1: Botón Hoy */}
        <div className="border-r border-gray-500 flex items-center justify-center">
          <button
            onClick={() => setDate(dayjs().toDate())}
            className="text-gray-500 hover:bg-gray-200 px-2 py-1 rounded flex items-center"
            aria-label="Ir a hoy"
            type="button"
          >
            {/* SVG inline (sin dependencias) */}
            <svg
              className="mr-1"
              width="16"
              height="16"
              viewBox="0 0 24 24"
              fill="none"
              aria-hidden="true"
            >
              <path
                d="M7 2v2M17 2v2M3.5 9h17M6 6h12a3 3 0 0 1 3 3v10a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V9a3 3 0 0 1 3-3Z"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
              />
            </svg>
            Hoy
          </button>
        </div>

        {/* Columnas 2-8: Días */}
        {days.map((day) => (
          <div
            key={day.date}
            id={`column-${day.date}`}
            className="text-center font-semibold flex flex-col items-center justify-center border-r border-gray-500"
          >
            <span className="capitalize">{day.dayName}</span>
            <span className="text-sm text-gray-500">{day.pretty}</span>
          </div>
        ))}
      </div>

      {/* Fila 2: Barras multi-día (dateStart/dateEnd) */}
      {multiDaySegments.length > 0 ? (
        <div
          className="grid w-full border-b border-gray-500 bg-white"
          style={{ gridTemplateColumns: "80px repeat(7, 1fr)" }}
        >
          <div className="border-r border-gray-500" />
          <div className="relative col-span-7 h-8">
            {multiDaySegments.map((segment, index) => {
              const key =
                (segment && segment.id) ||
                `${segment && segment.dateStart}-${segment && segment.dateEnd}-${index}`;

              return (
                <MultiDaySegmentsBar
                  key={key}
                  segment={segment}
                  startDate={startDate}
                  index={index}
                />
              );
            })}
          </div>
        </div>
      ) : null}

      {/* Fila 3+: Grilla de horas + overlay de segmentos */}
      <div className="relative">
        {/* Grilla base */}
        <div
          className="grid w-full border-t border-gray-500"
          style={{ gridTemplateColumns: "80px repeat(7, 1fr)" }}
        >
          {timeSlots.map((slot, i) => (
            <React.Fragment key={i}>
              {/* Columna 1: Hora */}
              <div
                id={`hour-${slot}`}
                className="text-xs text-gray-500 border-b border-r border-gray-500 flex items-center justify-center relative"
                style={{ height: BLOCK_HEIGHT }}
              >
                {slot}
                {currentSlot && currentSlot.id === slot ? (
                  <div
                    className="absolute left-0 right-0 h-[2px] bg-[#3a94cc]"
                    style={{ top: `${(currentSlot.minute / 60) * BLOCK_HEIGHT}px` }}
                  />
                ) : null}
              </div>

              {/* Columnas 2-8: Celdas */}
              {days.map((day, j) => (
                <div
                  key={`${i}-${j}`}
                  id={`cell-${day.date}-${slot}`}
                  className="border-b border-r border-gray-500"
                  style={{ height: `${BLOCK_HEIGHT}px` }}
                />
              ))}
            </React.Fragment>
          ))}
        </div>

        {/* Overlay de segmentos (start/end en HH:mm) */}
        <div
          className="absolute top-0 left-0 w-full pointer-events-none grid"
          style={{
            gridTemplateColumns: "80px repeat(7, 1fr)",
            height: totalHeight,
          }}
        >
          <div /> {/* columna de horas */}
          {days.map((day) => {
            const dayData = (scheduleByDate && scheduleByDate[day.date]) || {};
            const segments = Array.isArray(dayData && dayData.segments) ? dayData.segments : [];
            const extraDaySegments = Array.isArray(dayData && dayData.extraSegments)
              ? dayData.extraSegments
              : [];

            return (
              <div key={day.date} className="relative" style={{ height: totalHeight }}>
                <DaySegmentsRenderer
                  segments={segments}
                  extraSegments={extraDaySegments}
                  blockHeight={BLOCK_HEIGHT}
                  minHour="00:00"
                />
              </div>
            );
          })}
        </div>
      </div>

      {/* endDate se mantiene por contrato, aunque aquí no se use directamente */}
      <span className="sr-only">{endDate}</span>
    </div>
  );
};

export default ScheduleGridDesktop;
