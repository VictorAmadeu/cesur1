import React from "react";

const typeMap = {
    1: { title: "Almuerzo", color: "bg-yellow-500" },
    2: { title: "Descanso", color: "bg-green-500" },
    3: { title: "Hora extra", color: "bg-red-500" },
    4: { title: "Evento", color: "bg-orange-500" },
    99: { title: "Horario", color: "bg-blue-500" },
};

const ExtraSegmentRender = ({ start, end, top, height, type, width = "100%", left = "0px" }) => {
    const typeInfo = typeMap[type] || { title: "Otro", color: "bg-gray-400" };

    return (
        <div
            className={`absolute text-white rounded-lg px-3 py-2 text-sm shadow-md ${typeInfo.color}`}
            style={{
                top,
                height,
                width,
                left,
            }}
        >
            <strong>{typeInfo.title}</strong>
            <div className="text-xs">{start} - {end}</div>
        </div>
    );
};

export default ExtraSegmentRender;
