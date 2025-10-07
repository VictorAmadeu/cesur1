import React from "react";
import dayjs from '/imports/utils/dayjsConfig';

const ExtraSegmentsRenderer = ({ startDate, days, segments }) => {
    return (
        <div className="absolute inset-0 pointer-events-none">
            {segments.map(seg => {
                const startIndex = days.findIndex(d =>
                    dayjs(d.date).isSameOrAfter(seg.dateStart, "day")
                );
                const endIndex = days.findIndex(d =>
                    dayjs(d.date).isSameOrAfter(seg.dateEnd, "day")
                );

                if (startIndex === -1 || endIndex === -1 || startIndex > endIndex) return null;

                return (
                    <div
                        key={seg.id}
                        className="absolute bg-green-400 text-white text-xs px-2 py-0.5 rounded overflow-hidden"
                        style={{
                            top: 0,
                            left: `calc(${(startIndex + 1)} * 100% / 8)`,
                            width: `calc(${(endIndex - startIndex + 1)} * 100% / 8)`,
                            height: 20,
                        }}
                    >
                        {seg.name || `Segmento ${seg.id}`}
                    </div>
                );
            })}
        </div>
    );
};

export default ExtraSegmentsRenderer;
