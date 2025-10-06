import React, { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import dayjs from "../dayjsConfig";
import { useDate } from "/imports/provider/date";

const MonthGrid = ({ time }) => {
  const { setDate, getSelectedMonth } = useDate();
  const navigate = useNavigate();
  const today = dayjs().format("YYYY-MM-DD");
  const [daysInMonth, setDaysInMonth] = useState([]);

  // Generar el calendario al cambiar el mes o el tiempo
  const generateCalendar = () => {
    const month = getSelectedMonth();
    const startOfMonth = dayjs(month).startOf("month");
    const endOfMonth = dayjs(month).endOf("month");

    // Calcular el primer día de la semana del mes (Lunes)
    const firstDayOfMonth = startOfMonth.startOf("isoWeek"); // Start from Monday

    // Generar todos los días que deben mostrarse en el calendario
    const days = [];
    let currentDay = firstDayOfMonth;
    while (currentDay.isBefore(endOfMonth.endOf("isoWeek"))) {
      days.push(currentDay.format("YYYY-MM-DD"));
      currentDay = currentDay.add(1, "day");
    }

    setDaysInMonth(days);
  };

  // Ejecutar generateCalendar cuando time cambie
  useEffect(() => {
    generateCalendar();
  }, [time]); // Dependiendo de time

  const handleClick = (date) => {
    const newDate = dayjs(date).toDate();
    setDate(newDate);
    navigate("/registrar-tiempo");
  };

  const weekdays = ["Lun", "Mar", "Mié", "Jue", "Vie", "Sáb", "Dom"]; // Ahora empezamos por Lunes

  return (
    <div className="w-full">
      {/* Fila con los días de la semana */}
      <div className="grid grid-cols-7 gap-2 text-center font-bold mb-2">
        {weekdays.map((day, index) => (
          <div key={index} className="text-sm text-gray-700">
            {day}
          </div>
        ))}
      </div>

      {/* El calendario con los días del mes */}
      <div className="grid grid-cols-7 gap-2">
        {daysInMonth.map((date, index) => {
          const isToday = dayjs(date).format("YYYY-MM-DD") === today;
          const isPast = dayjs(date).isBefore(today, "day");
          const isFuture = dayjs(date).isAfter(today, "day");
          const isSelectedMonth = dayjs(date).isSame(
            dayjs(getSelectedMonth()),
            "month"
          );
          const dayOfMonth = dayjs(date).format("D");

          // Comprobar si es un día fuera del mes seleccionado (en blanco)
          const isBlank = !isSelectedMonth;

          // Buscar el tiempo total para cada día
          const totalTime =
            time.find((entry) => dayjs(entry.date).isSame(dayjs(date), "day"))
              ?.totalTime || "00:00:00";

          return (
            <div
              key={index}
              className={`flex flex-col items-center justify-center p-2 min-h-16 ${isBlank
                ? "bg-white border-none" // Días fuera del mes serán vacíos sin nada
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
              {/* Mostrar solo el día del mes si es parte del mes */}
              {!isBlank && (
                <div>
                  <span
                    style={{
                      color: isToday ? "white" : isPast ? "#3498db" : "#3498db",
                      fontWeight: "bold",
                      fontSize: "16px",
                    }}
                  >
                    {dayOfMonth}
                  </span>
                </div>
              )}
              {/* Mostrar el tiempo solo si es un día válido dentro del mes */}
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
