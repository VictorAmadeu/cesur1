import React, { createContext, useState, useContext, useEffect } from "react";
import dayjs from "../utils/dayjsConfig";

/**
 * DateContext centraliza la fecha seleccionada y utilidades de calendario.
 * ⚠️ Producción: mantenemos contratos existentes (getSelectedMonth -> "YYYY-MM")
 * y añadimos helpers nuevos para evitar romper otros consumidores.
 */
// @ts-ignore
const DateContext = createContext();

export const DateProvider = ({ children }) => {
  // Usamos la fecha actual como punto de partida
  const [selectedDate, setSelectedDate] = useState(dayjs().toDate());
  const [selectedYear, setSelectedYear] = useState(new Date().getFullYear());

  // Flags de conveniencia para UI
  const [isCurrentYear, setIsCurrentYear] = useState(false);
  const [isCurrentMonth, setIsCurrentMonth] = useState(false);
  const [isCurrentDay, setIsCurrentDay] = useState(false);

  // Rango de semana ISO (lunes-domingo) para vistas que lo requieran
  const [rangeWeek, setRangeWeek] = useState({ start: null, end: null });

  /** Establece la fecha seleccionada y actualiza el año */
  const setDate = (date) => {
    setSelectedDate(date);
    setSelectedYear(dayjs(date).year());
  };

  /** Establece el año (por si se selecciona desde un selector de año) */
  const setYear = (year) => {
    setSelectedYear(year);
  };

  /**
   * 🔵 CONTRATO EXISTENTE (NO ROMPER):
   * Devuelve el mes seleccionado en formato "YYYY-MM".
   * (Se corrigió previamente el espacio final en Paso 1)
   */
  const getSelectedMonth = () => {
    return dayjs(selectedDate).format("YYYY-MM");
  };

  /**
   * 🆕 NUEVO HELPER (robusto para cálculo):
   * Devuelve el PRIMER día del mes como "YYYY-MM-DD".
   * Útil para anclar cálculos de cuadrícula a una fecha real (no string ambigua).
   */
  const getSelectedMonthISO = () => {
    return dayjs(selectedDate).startOf("month").format("YYYY-MM-DD");
  };

  /**
   * 🆕 Útil para validaciones/batería de pruebas:
   * Rango completo del mes en ISO (YYYY-MM-DD).
   */
  const getSelectedMonthRangeISO = () => {
    const start = dayjs(selectedDate).startOf("month");
    const end = dayjs(selectedDate).endOf("month");
    return {
      start: start.format("YYYY-MM-DD"),
      end: end.format("YYYY-MM-DD"),
    };
  };

  useEffect(() => {
    const currentDate = dayjs();
    const selectedMonth = dayjs(selectedDate);

    setIsCurrentMonth(currentDate.isSame(selectedMonth, "month"));
    setIsCurrentDay(currentDate.isSame(selectedDate, "day"));
    setIsCurrentYear(currentDate.year() === selectedYear);

    // Semana ISO (lunes a domingo) basada en la fecha seleccionada
    const startOfWeek = selectedMonth.startOf("isoWeek");
    const endOfWeek = selectedMonth.endOf("isoWeek");
    setRangeWeek({
      start: startOfWeek.format("YYYY-MM-DD"),
      end: endOfWeek.format("YYYY-MM-DD"),
    });
  }, [selectedDate, selectedYear]);

  return (
    <DateContext.Provider
      value={{
        // Estado base
        selectedDate,
        setDate,
        selectedYear,
        setYear,

        // Flags de conveniencia
        isCurrentMonth,
        isCurrentDay,
        isCurrentYear,

        // API pública (mantener contratos)
        getSelectedMonth,          // "YYYY-MM" (contrato antiguo)
        getSelectedMonthISO,       // "YYYY-MM-DD" (nuevo, robusto)
        getSelectedMonthRangeISO,  // { start, end } (YYYY-MM-DD)

        // Semana ISO
        rangeWeek,
        setRangeWeek,
      }}
    >
      {children}
    </DateContext.Provider>
  );
};

export const useDate = () => useContext(DateContext);
