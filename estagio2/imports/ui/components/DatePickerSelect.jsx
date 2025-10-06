import React from "react";
import DatePicker from "react-datepicker";
import "react-datepicker/dist/react-datepicker.css";
import utc from "dayjs/plugin/utc";
import timezone from "dayjs/plugin/timezone";
import isoWeek from "dayjs/plugin/isoWeek";
import updateLocale from "dayjs/plugin/updateLocale";
import { useDate } from "../../provider/date";
import dayjs, { capitalize } from "../../utils/dayjsConfig";
import { es } from "date-fns/locale";
import { ChevronLeft, ChevronRight } from "lucide-react";

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

  const current = dayjs(date);

  const changeDateByType = (op = "add") => {
    const operations = {
      week: () => {
        const newDate = current[op](1, "week");
        const weekStart = newDate.startOf("isoWeek");
        const weekEnd = newDate.endOf("isoWeek");

        setDate(newDate.toDate());
        setRangeWeek({ start: weekStart.format("YYYY-MM-DD"), end: weekEnd.format("YYYY-MM-DD") });
      },
      date: () => setDate(current[op](1, "day").toDate()),
      month: () => setDate(current[op](1, "month").toDate()),
      year: () => setYear(year + (op === "add" ? 1 : -1)),
    };

    operations[type]?.();
  };

  const handleBack = () => changeDateByType("subtract");
  const handleForward = () => changeDateByType("add");

  const isForwardDisabled = !allowFutureDates && {
    date: isCurrentDay,
    month: isCurrentMonth,
    year: isCurrentYear,
    week: current.isSame(dayjs(), "week"),
  }[type];

  const renderLabel = () => {
    const labelGenerators = {
      date: () => capitalize(current.format("D [de] MMMM [de] YYYY")),
      month: () => capitalize(current.format("MMMM [de] YYYY")),
      year: () => year.toString(),
      week: () => {
        const start = current.startOf("isoWeek");
        const end = current.endOf("isoWeek");
        const sameMonth = start.month() === end.month();

        return sameMonth
          ? `${start.format("D")} - ${capitalize(end.format("D [de] MMMM [de] YYYY"))}`
          : `${capitalize(start.format("D [de] MMMM"))} - ${end.format("D [de] MMMM [de] YYYY")}`;
      },
    };

    return labelGenerators[type]?.() || "";
  };

  const handleChange = (newDate) => {
    const d = dayjs(newDate);

    if (type === "year") {
      setYear(d.year());
    } else if (type === "week") {
      setDate(d.startOf("isoWeek").toDate());
      const start = d.startOf("isoWeek");
      const end = d.endOf("isoWeek");
      setRangeWeek({ start: start.format("YYYY-MM-DD"), end: end.format("YYYY-MM-DD") });
    } else {
      setDate(d.toDate());
    }
  };

  return (
    <div className="flex flex-col items-center">
      <div className="w-full flex items-center justify-between gap-2 my-2">
        <button
          className="p-2 rounded-full hover:bg-gray-200 transition"
          onClick={handleBack}
        >
          <ChevronLeft />
        </button>

        <DatePicker
          selected={type === "year" ? new Date(year, 0) : date}
          onChange={handleChange}
          calendarStartDay={1}
          showMonthYearPicker={type === "month"}
          showYearPicker={type === "year"}
          maxDate={allowFutureDates ? null : dayjs().toDate()}
          customInput={
            <div className="cursor-pointer border border-gray-300 rounded-md px-4 py-1 font-medium bg-white hover:bg-gray-100 transition text-center w-full">
              {renderLabel()}
            </div>
          }
          locale={es}
          popperPlacement="bottom-start"
          popperModifiers={[
            { name: "preventOverflow", options: { boundary: "window" } },
          ]}
          withPortal
          portalId="root"
        />

        <button
          className="p-2 rounded-full hover:bg-gray-200 transition disabled:opacity-50 disabled:cursor-not-allowed"
          onClick={handleForward}
          disabled={isForwardDisabled}
        >
          <ChevronRight />
        </button>
      </div>
    </div>
  );
};
