import React, { useEffect, useState } from "react";
import { TbCalendarUp } from "react-icons/tb";
import { useDate } from "../../../../provider/date";
import dayjs from '/imports/utils/dayjsConfig';
import MultiDaySegmentsBar from "./MultiDaySegmentsBar";

const ScheduleGridDesktop = ({
    startDate,
    endDate,
    scheduleByDate,
    extraSegments,
}) => {
    const BLOCK_HEIGHT = 60;
    const timeSlots = Array.from({ length: 24 }, (_, i) =>
        `${String(i).padStart(2, "0")}:00`
    );

    const { setDate } = useDate();
    const [currentSlot, setCurrentSlot] = useState(null);

    useEffect(() => {
        const updateTime = () => {
            const now = dayjs();
            const hour = now.hour().toString().padStart(2, "0");
            const minute = now.minute();
            const slot = `${hour}:00`;
            setCurrentSlot({ id: slot, minute });
        };

        console.log(startDate, endDate);
        console.log(scheduleByDate);
        console.log(extraSegments);

        updateTime();
        const interval = setInterval(updateTime, 60 * 1000);
        return () => clearInterval(interval);
    }, []);

    const multiDaySegments = extraSegments?.segments?.filter(
        s => dayjs(s.dateEnd).diff(dayjs(s.dateStart), "day") > 0
    ) || [];

    return (
        <div className="w-full mb-4 overflow-hidden border-t border-gray-500">
            {/* Fila 1: Botón Hoy + Cabecera de días */}
            <div className="grid w-full h-[60px] border-b border-gray-500 bg-white" style={{ gridTemplateColumns: "80px repeat(7, 1fr)" }}>
                {/* Columna 1: Botón Hoy */}
                <div className="border-r border-gray-500 flex items-center justify-center">
                    <button
                        onClick={() => setDate(dayjs().format("YYYY-MM-DD"))}
                        className="text-gray-500 hover:bg-gray-200 px-2 py-1 rounded flex items-center"
                    >
                        <TbCalendarUp className="mr-1" /> Hoy
                    </button>
                </div>

                {/* Columnas 2-6: Días */}
                {Array.from({ length: 7 }).map((_, i) => {
                    const date = dayjs(startDate).add(i, "day");
                    const dayName = date.format("dddd");
                    const formatted = date.format("YYYY-MM-DD");

                    return (
                        <div
                            key={formatted}
                            id={`column-${formatted}`}
                            className="text-center font-semibold flex flex-col items-center justify-center border-r border-gray-500"
                        >
                            <span className="capitalize">{dayName}</span>
                            <span className="text-sm text-gray-500">{date.format("DD/MM")}</span>
                        </div>
                    );
                })}
            </div>

            {/* Fila 2: Segmentos multi-día */}
            {extraSegments && extraSegments?.segments && extraSegments?.segments?.length > 0 ? (
                <div
                    className="w-full h-fit"
                    style={{
                        display: "grid",
                        gridTemplateColumns: "80px repeat(7, 1fr)",
                        minHeight: `${extraSegments?.segments?.length * 8}px`, // Altura total
                    }}
                >
                    <div /> {/* Columna vacía para horas */}
                    <div className="col-span-7 w-full">
                        {extraSegments.segments.map((segment, index) => (
                            <MultiDaySegmentsBar
                                key={segment.id}
                                segment={segment}
                                startDate={startDate}
                                index={index}
                            />
                        ))}
                    </div>

                </div>
            ) : null}

            {/* Fila 3+: Grilla de horas + celdas */}
            <div className="grid w-full border-t border-gray-500" style={{ gridTemplateColumns: "80px repeat(7, 1fr)" }}>
                {timeSlots.map((slot, i) => (
                    <React.Fragment key={i}>
                        {/* Columna 1: Hora */}
                        <div
                            id={`hour-${slot}`}
                            className="text-xs text-gray-500 border-b border-r border-gray-500 flex items-center justify-center relative"
                            style={{ height: BLOCK_HEIGHT }}
                        >
                            {slot}
                            {currentSlot?.id === slot && (
                                <div
                                    className="absolute left-0 right-0 h-[2px] bg-[#3a94cc]"
                                    style={{
                                        top: `${(currentSlot.minute / 60) * BLOCK_HEIGHT}px`,
                                    }}
                                />
                            )}
                        </div>

                        {/* Columnas 2-6: Celdas vacías */}
                        {Array.from({ length: 7 }).map((_, j) => {
                            const date = dayjs(startDate).add(j, "day").format("YYYY-MM-DD");
                            return (
                                <div
                                    key={`${i}-${j}`}
                                    id={`cell-${date}-${slot}`}
                                    className="border-b border-r border-gray-500"
                                    style={{ height: `${BLOCK_HEIGHT}px` }}
                                />
                            );
                        })}
                    </React.Fragment>
                ))}
            </div>
        </div>
    );
};

export default ScheduleGridDesktop;
