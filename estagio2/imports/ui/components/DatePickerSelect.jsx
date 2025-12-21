import React from "react";
import { Meteor } from "meteor/meteor";
import DatePicker from "react-datepicker";
// @ts-ignore  — CSS side-effect import; el bundler lo gestiona, TS no tiene tipos
import "react-datepicker/dist/react-datepicker.css";

// Estos imports de plugins suelen activarse en la config central de dayjs.
// Se conservan aquí como estaban para no alterar producción.
import utc from "dayjs/plugin/utc";
import timezone from "dayjs/plugin/timezone";
import isoWeek from "dayjs/plugin/isoWeek";
import updateLocale from "dayjs/plugin/updateLocale";

import { useDate } from "../../provider/date";
import dayjs, { capitalize } from "../../utils/dayjsConfig";

// Locale ES para react-datepicker (solo afecta al popup/calendario)
import { es } from "date-fns/locale";
import { ChevronLeft, ChevronRight } from "lucide-react";

/**
 * DatePickerSelect
 * ----------------
 * Selector de fecha/mes/año/semana con:
 * - Navegación con flechas (izq/der).
 * - Formato local ES (día de la semana cuando type === "date").
 * - Control de no permitir fechas futuras (si allowFutureDates === false).
 *
 * Props:
 * - type: "date" | "month" | "year" | "week"
 * - allowFutureDates?: boolean (por defecto: false)
 */
export const DatePickerSelect = ({ type, allowFutureDates = false }) => {
  const {
    selectedDate: date,
    setDate,
    selectedYear: year,
    setYear,
    isCurrentMonth,
    isCurrentDay,
    isCurrentYear,
    rangeWeek,
    setRangeWeek,
  } = useDate();

  let safeDate = date instanceof Date ? date : dayjs(date).toDate();
  if (Number.isNaN(safeDate.getTime())) {
    safeDate = dayjs().toDate();
  }
  const current = dayjs(safeDate);

  const isCordova = typeof Meteor !== "undefined" && Meteor.isCordova;

  const getWeekInputValue = (dateValue) => {
    const weekYear = dateValue.isoWeekYear();
    const weekNumber = dateValue.isoWeek();
    return `${weekYear}-W${String(weekNumber).padStart(2, "0")}`;
  };

  const getNativeValue = () => {
    if (type === "year") return String(year);
    if (type === "week") return getWeekInputValue(current);
    if (type === "month") return current.format("YYYY-MM");
    return current.format("YYYY-MM-DD");
  };

  const getNativeMax = () => {
    if (allowFutureDates) return undefined;
    const today = dayjs();
    if (type === "year") return String(today.year());
    if (type === "week") return getWeekInputValue(today);
    if (type === "month") return today.format("YYYY-MM");
    return today.format("YYYY-MM-DD");
  };

  const handleNativeChange = (event) => {
    const { value } = event.target;
    if (!value) return;

    if (type === "year") {
      const nextYear = Number(value);
      if (!Number.isNaN(nextYear)) setYear(nextYear);
      return;
    }

    if (type === "week") {
      const [yearPart, weekPart] = value.split("-W");
      const weekYear = Number(yearPart);
      const weekNumber = Number(weekPart);
      if (Number.isNaN(weekYear) || Number.isNaN(weekNumber)) return;

      const weekStart = dayjs()
        .isoWeekYear(weekYear)
        .isoWeek(weekNumber)
        .startOf("isoWeek");

      setDate(weekStart.toDate());
      setRangeWeek({
        start: weekStart.format("YYYY-MM-DD"),
        end: weekStart.endOf("isoWeek").format("YYYY-MM-DD"),
      });
      return;
    }

    const formatByType = {
      date: "YYYY-MM-DD",
      month: "YYYY-MM",
    };
    const parsed = dayjs(value, formatByType[type]);
    if (!parsed.isValid()) return;

    if (type === "month") {
      setDate(parsed.startOf("month").toDate());
    } else {
      setDate(parsed.startOf("day").toDate());
    }
  };

  /**
   * Cambia la fecha según el tipo de selector:
   * - date  ⇒ +/- 1 día
   * - month ⇒ +/- 1 mes
   * - year  ⇒ +/- 1 año (operando sobre selectedYear)
   * - week  ⇒ +/- 1 semana, y recalcula el rango ISO (lun-dom)
   */
  const changeDateByType = (op = "add") => {
    const operations = {
      week: () => {
        const newDate = current[op](1, "week");
        const weekStart = newDate.startOf("isoWeek");
        const weekEnd = newDate.endOf("isoWeek");

        setDate(newDate.toDate());
        setRangeWeek({
          start: weekStart.format("YYYY-MM-DD"),
          end: weekEnd.format("YYYY-MM-DD"),
        });
      },
      date: () => setDate(current[op](1, "day").toDate()),
      month: () => setDate(current[op](1, "month").toDate()),
      year: () => setYear(year + (op === "add" ? 1 : -1)),
    };

    operations[type]?.();
  };

  const handleBack = () => changeDateByType("subtract");
  const handleForward = () => changeDateByType("add");

  // Desactiva flecha "adelante" cuando no se permiten futuras
  const isForwardDisabled =
    !allowFutureDates &&
    {
      date: isCurrentDay,
      month: isCurrentMonth,
      year: isCurrentYear,
      week: current.isSame(dayjs(), "week"),
    }[type];

  /**
   * Etiqueta visible del input (customInput):
   * - Para "date": añadimos el DÍA DE LA SEMANA (dddd).
   *   Ej.: "lunes, 27 de octubre de 2025"
   * - Para "week": mostramos el rango ISO (lun-dom).
   * - Para "month"/"year": formatos habituales.
   */
  const renderLabel = () => {
    const labelGenerators = {
      // ✅ Cambio principal: añadimos 'dddd,' para mostrar día de la semana.
      date: () => capitalize(current.format("dddd, D [de] MMMM [de] YYYY")),
      month: () => capitalize(current.format("MMMM [de] YYYY")),
      year: () => year.toString(),
      week: () => {
        const start = current.startOf("isoWeek");
        const end = current.endOf("isoWeek");
        const sameMonth = start.month() === end.month();

        return sameMonth
          ? `${start.format("D")} - ${capitalize(
              end.format("D [de] MMMM [de] YYYY")
            )}`
          : `${capitalize(
              start.format("D [de] MMMM")
            )} - ${end.format("D [de] MMMM [de] YYYY")}`;
      },
    };

    return labelGenerators[type]?.() || "";
  };

  /**
   * onChange del DatePicker:
   * - year  ⇒ actualiza selectedYear
   * - week  ⇒ setea fecha al inicio de semana (ISO) y calcula su rango
   * - otros ⇒ setea la fecha directamente
   */
  const handleChange = (newDate) => {
    const d = dayjs(newDate);

    if (type === "year") {
      setYear(d.year());
    } else if (type === "week") {
      setDate(d.startOf("isoWeek").toDate());
      const start = d.startOf("isoWeek");
      const end = d.endOf("isoWeek");
      setRangeWeek({
        start: start.format("YYYY-MM-DD"),
        end: end.format("YYYY-MM-DD"),
      });
    } else {
      setDate(d.toDate());
    }
  };

  const renderCordovaInput = () => {
    const baseClass =
      "cursor-pointer border border-gray-300 rounded-md px-4 py-1 font-medium bg-white hover:bg-gray-100 transition text-center w-full";

    const commonProps = {
      className: baseClass,
      onChange: handleNativeChange,
      value: getNativeValue(),
      max: getNativeMax(),
      "aria-label": renderLabel(),
    };

    if (type === "year") {
      return (
        <input
          type="number"
          inputMode="numeric"
          {...commonProps}
        />
      );
    }

    if (type === "week") {
      return <input type="week" {...commonProps} />;
    }

    if (type === "month") {
      return <input type="month" {...commonProps} />;
    }

    return <input type="date" {...commonProps} />;
  };

  return (
    <div className="flex flex-col items-center">
      <div className="w-full flex items-center justify-between gap-2 my-2">
        {/* Flecha atrás */}
        <button
          className="p-2 rounded-full hover:bg-gray-200 transition"
          onClick={handleBack}
          aria-label="Anterior"
          type="button"
        >
          <ChevronLeft />
        </button>

        {/* DatePicker con input personalizado (renderLabel) */}
        {isCordova ? (
          renderCordovaInput()
        ) : (
        <DatePicker
          selected={type === "year" ? new Date(year, 0) : safeDate}
          onChange={handleChange}
          calendarStartDay={1}
          showMonthYearPicker={type === "month"}
          showYearPicker={type === "year"}
          // Si no se permiten fechas futuras, fijamos hoy como máximo
          maxDate={allowFutureDates ? null : dayjs().toDate()}
          customInput={
            <div className="cursor-pointer border border-gray-300 rounded-md px-4 py-1 font-medium bg-white hover:bg-gray-100 transition text-center w-full">
              {renderLabel()}
            </div>
          }
          locale={es}
          popperPlacement="bottom-start"
          // @ts-ignore — El objeto es válido en runtime; TS pide 'fn' por tipos de floating-ui
          popperModifiers={[{ name: "preventOverflow", options: { boundary: "window" } }]}
          withPortal
          portalId="root"
        />
        )}

        {/* Flecha adelante (se desactiva cuando proceda) */}
        <button
          className="p-2 rounded-full hover:bg-gray-200 transition disabled:opacity-50 disabled:cursor-not-allowed"
          onClick={handleForward}
          disabled={isForwardDisabled}
          aria-label="Siguiente"
          type="button"
        >
          <ChevronRight />
        </button>
      </div>
    </div>
  );
};
