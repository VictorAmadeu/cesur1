import React from "react";
import dayjs from "dayjs";
import { useNavigate } from "react-router-dom";
import { useDate } from "/imports/provider/date";
import { ArrowUpRight } from "lucide-react";

const DayList = ({ time }) => {
  const { setDate } = useDate();
  const navigate = useNavigate();
  const today = dayjs().format("YYYY-MM-DD");

  const handleClick = (date) => {
    const newDate = dayjs(date).toDate();
    setDate(newDate);
    navigate("/registrar-tiempo");
  };

  return (
    <div className="w-full flex flex-col gap-4">
      {time.map((entry, index) => {
        const entryDate = dayjs(entry.date).format("YYYY-MM-DD");
        const isToday = entryDate === today;
        const isPast = dayjs(entry.date).isBefore(today, "day");
        const isFuture = dayjs(entry.date).isAfter(today, "day");

        return (
          <div
            key={index}
            className="flex justify-between items-center px-4 py-2 border border-gray-200 rounded-md"
            style={{
              backgroundColor: isToday
                ? "#3a94cc"
                : isPast
                ? "#f1f8fd"
                : "white",
            }}
          >
            <div
              style={{
                color: isToday ? "white" : "#3498db",
                fontWeight: "bold",
              }}
            >
              {dayjs(entry.date).format("D")}
            </div>
            {!isFuture && (
              <div>
                {entry.totalTime === "00:00:00" ? (
                  <span style={{ color: isToday ? "white" : "inherit" }}>
                    No fichado
                  </span>
                ) : (
                  <span style={{ color: isToday ? "white" : "inherit" }}>
                    {entry.totalTime}
                  </span>
                )}
              </div>
            )}
            {!isFuture && (
              <button
                onClick={() => handleClick(entry.date)}
                className="flex gap-2 justify-center items-center"
              >
                <span style={{color: isToday ? "white" : "inherit"}}>Ver día</span>
                {isToday ? (<ArrowUpRight className="text-white" />) : (<ArrowUpRight />)}
              </button>
            )}
          </div>
        );
      })}
    </div>
  );
};

export default DayList;
