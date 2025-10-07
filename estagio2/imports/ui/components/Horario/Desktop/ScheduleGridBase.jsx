import React from "react";
import dayjs from '/imports/utils/dayjsConfig';
import DaySegmentsRenderer from "./DaySegmentsRenderer";

const ScheduleGridBase = ({ startDate, endDate, scheduleByDate, extraSegments }) => {
    const BLOCK_HEIGHT = 60;

    // 1. Obtener los 7 días de la semana
    const days = Array.from({ length: 7 }).map((_, i) => {
        const date = dayjs(startDate).add(i, "day");
        return {
            date: date.format("YYYY-MM-DD"),
            label: date.format("dddd DD/MM"),
        };
    });

    let minHour = "08:00";
    let maxHour = "20:00";

    // 2. Calcular el rango total de horas (mínima y máxima en toda la semana)
    const allHours = [minHour, maxHour];

    minHour = allHours.reduce((min, h) =>
        dayjs(h, "HH:mm").isBefore(dayjs(min, "HH:mm")) ? h : min
    );

    maxHour = allHours.reduce((max, h) =>
        dayjs(h, "HH:mm").isAfter(dayjs(max, "HH:mm")) ? h : max
    );

    // 3. Generar slots horarios
    const hourStart = dayjs(minHour, "HH:mm");
    const hourEnd = dayjs(maxHour, "HH:mm");
    const hours = [];

    for (let t = hourStart; t.isBefore(hourEnd); t = t.add(1, "hour")) {
        hours.push(t.format("HH:mm"));
    }

    const totalHeight = hours.length * BLOCK_HEIGHT;

    return (
        <div className="w-full border">
            {/* Header de días */}
            <div className="grid" style={{ gridTemplateColumns: `80px repeat(7, 1fr)` }}>
                <div className="bg-gray-100 p-2 text-center font-semibold">Horas</div>
                {days.map(day => (
                    <div key={day.date} className="bg-gray-100 p-2 text-center font-semibold">
                        {day.label}
                    </div>
                ))}
            </div>

            {/* Grilla de horas x días */}
            <div className="relative">
                {hours.map(hour => (
                    <div key={hour} className="grid border-t" style={{ gridTemplateColumns: `80px repeat(7, 1fr)`, height: BLOCK_HEIGHT }}>
                        <div className="text-xs text-center text-gray-500 border-r flex items-center justify-center">
                            {hour}
                        </div>
                        {days.map((day, dayIndex) => (
                            <div
                                key={day.date + hour}
                                className="border-r border-gray-200 relative"
                                style={{ backgroundColor: "#fff" }}
                            >
                                {/* Nada acá, el renderer va por día */}
                            </div>
                        ))}
                    </div>
                ))}

                {/* Renderizar segmentos una sola vez por día */}
                <div
                    className="absolute top-0 left-0 w-full pointer-events-none grid"
                    style={{
                        gridTemplateColumns: `80px repeat(7, 1fr)`,
                        height: totalHeight, // 🔸 agregá esto
                    }}
                >
                    <div /> {/* espacio de horas */}
                    {days.map((day, i) => (
                        <div key={i} className="relative h-full" style={{ height: totalHeight }}>
                            <DaySegmentsRenderer
                                segments={scheduleByDate[day.date]?.segments || []}
                                blockHeight={BLOCK_HEIGHT}
                                minHour={minHour}
                            />
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
};

export default ScheduleGridBase;
