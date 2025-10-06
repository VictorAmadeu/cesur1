import React, { createContext, useState, useContext, useEffect } from "react";
import dayjs from "../utils/dayjsConfig";

// @ts-ignore
const DateContext = createContext();

export const DateProvider = ({ children }) => {
  const [selectedDate, setSelectedDate] = useState(dayjs().toDate());
  const [selectedYear, setSelectedYear] = useState(new Date().getFullYear());
  const [isCurrentYear, setIsCurrentYear] = useState(false);
  const [isCurrentMonth, setIsCurrentMonth] = useState(false);
  const [isCurrentDay, setIsCurrentDay] = useState(false);
  const [rangeWeek, setRangeWeek] = useState({ start: null, end: null });

  const setDate = (date) => {
    setSelectedDate(date);
    setSelectedYear(dayjs(date).year()); // Actualizar el año seleccionado
  };

  const setYear = (year) => {
    setSelectedYear(year);
  };

  // Nueva función que devuelve el mes seleccionado en formato de texto
  const getSelectedMonth = () => {
    return dayjs(selectedDate).format("YYYY-MM ");
  };

  useEffect(() => {
    const currentDate = dayjs();
    const selectedMonth = dayjs(selectedDate);
    setIsCurrentMonth(currentDate.isSame(selectedMonth, "month"));
    setIsCurrentDay(currentDate.isSame(selectedDate, "day"));
    setIsCurrentYear(currentDate.year() === selectedYear);

    // NUEVO: Calcular inicio y fin de la semana
    const startOfWeek = dayjs(selectedDate).startOf("isoWeek"); // lunes
    const endOfWeek = dayjs(selectedDate).endOf("isoWeek");     // domingo
    setRangeWeek({
      start: startOfWeek.format("YYYY-MM-DD"),
      end: endOfWeek.format("YYYY-MM-DD")
    });
  }, [selectedDate, selectedYear]);

  return (
    <DateContext.Provider
      value={{
        selectedDate,
        setDate,
        selectedYear,
        setYear,
        isCurrentMonth,
        isCurrentDay,
        isCurrentYear,
        getSelectedMonth,
        rangeWeek,
        setRangeWeek,
      }}
    >
      {children}
    </DateContext.Provider>
  );
};

export const useDate = () => useContext(DateContext);
