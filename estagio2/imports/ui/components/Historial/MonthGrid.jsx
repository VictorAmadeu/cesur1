import React, { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import dayjs from "/imports/utils/dayjsConfig";
import { useDate } from "/imports/provider/date";

/**
 * MonthGrid
 * Genera una cuadrícula mensual robusta:
 *  - Anclada en selectedDate (objeto Day.js)
 *  - startOf('month') / endOf('month')
 *  - Extensión a semana ISO completa (lunes-domingo) con startOf('isoWeek') / endOf('isoWeek')
 *  - Fechas almacenadas como "YYYY-MM-DD" para evitar ambigüedades
 */
const MonthGrid = ({ time }) => {
  // ⚠️ Usamos selectedDate (objeto) para evitar parseos de string ambiguos.
  const { selectedDate, setDate } = useDate();
  const navigate = useNavigate();

  // Fecha de hoy (para estilos y lógica de click)
  const today = dayjs().format("YYYY-MM-DD");

  // Lista de días a pintar en la cuadricula (incluyendo arrastre prev/next)
  const [daysInMonth, setDaysInMonth] = useState([]);

  /** Genera la cuadrícula mensual completa (de lunes a domingo) */
  const generateCalendar = () => {
    // Referencia: fecha seleccionada por el usuario (objeto Day.js)
    const monthDate = dayjs(selectedDate);

    // Límites estrictos del mes
    const startOfMonth = monthDate.startOf("month");
    const endOfMonth = monthDate.endOf("month");

    // Extensión a semana ISO (empieza en lunes) para mostrar una cuadrícula "completa"
    const firstDay = startOfMonth.startOf("isoWeek");
    const lastDay = endOfMonth.endOf("isoWeek");

    // Rellena día a día desde 'firstDay' hasta 'lastDay' (ambos inclusive)
    const days = [];
    let current = firstDay;
    while (current.isSameOrBefore(lastDay, "day")) {
      days.push(current.format("YYYY-MM-DD"));
      current = current.add(1, "day"); // Day.js es inmutable; reasignamos 'current'
    }

    setDaysInMonth(days);
  };

  // Regenera la cuadrícula cuando cambie la fuente de tiempos o la fecha seleccionada
  useEffect(() => {
    generateCalendar();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [time, selectedDate]);

  /** Click en un día válido: fija fecha y navega a registrar tiempo */
  const handleClick = (date) => {
    const newDate = dayjs(date).toDate();
    setDate(newDate);
    navigate("/registrar-tiempo");
  };

  // Semanario empezando en lunes (ISO)
  const weekdays = ["Lun", "Mar", "Mié", "Jue", "Vie", "Sáb", "Dom"];

  // Clave de mes seleccionado para comparación rápida (evita recomputar en cada render)
  const selectedMonthKey = dayjs(selectedDate).format("YYYY-MM");

  return (
    <div className="w-full">
      {/* Cabecera con etiquetas de días */}
      <div className="grid grid-cols-7 gap-2 text-center font-bold mb-2">
        {weekdays.map((day, index) => (
          <div key={index} className="text-sm text-gray-700">
            {day}
          </div>
        ))}
      </div>

      {/* Cuadrícula mensual */}
      <div className="grid grid-cols-7 gap-2">
        {daysInMonth.map((date, index) => {
          const dateObj = dayjs(date);
          const isToday = dateObj.format("YYYY-MM-DD") === today;
          const isPast = dateObj.isBefore(today, "day");
          const isFuture = dateObj.isAfter(today, "day");

          // Día pertenece al mes seleccionado (clave "YYYY-MM")
          const isSelectedMonth = dateObj.format("YYYY-MM") === selectedMonthKey;
          const dayOfMonth = dateObj.format("D");

          // Día fuera del mes → se pinta "en blanco"
          const isBlank = !isSelectedMonth;

          // Buscar el tiempo total de ese día (si existe)
          const totalTime =
            time.find((entry) => dayjs(entry.date).isSame(dateObj, "day"))
              ?.totalTime || "00:00:00";

          return (
            <div
              key={index}
              className={`flex flex-col items-center justify-center p-2 min-h-16 ${
                isBlank
                  ? "bg-white border-none" // fuera de mes: celdas "vacías"
                  : isPast
                  ? "bg-[#f1f8fd] cursor-pointer rounded-md border border-gray-200"
                  : isToday
                  ? "bg-[#3a94cc] cursor-pointer rounded-md border border-gray-200"
                  : "bg-white cursor-not-allowed rounded-md border border-gray-200"
              }`}
              onClick={
                !isBlank && (isPast || isToday)
                  ? () => handleClick(date)
                  : undefined
              }
            >
              {/* Número de día (solo si pertenece al mes) */}
              {!isBlank && (
                <div>
                  <span
                    style={{
                      color: isToday ? "white" : "#3498db",
                      fontWeight: "bold",
                      fontSize: "16px",
                    }}
                  >
                    {dayOfMonth}
                  </span>
                </div>
              )}

              {/* Texto inferior: total de tiempo o "No fichado" (solo pasado/hoy y dentro del mes) */}
              {!isBlank && (isPast || isToday) && (
                <div className="text-xs">
                  {totalTime === "00:00:00" ? (
                    <span style={{ color: isToday ? "white" : "inherit" }}>
                      No fichado
                    </span>
                  ) : (
                    <span style={{ color: isToday ? "white" : "inherit" }}>
                      {totalTime}
                    </span>
                  )}
                </div>
              )}
            </div>
          );
        })}
      </div>
    </div>
  );
};

export default MonthGrid;
