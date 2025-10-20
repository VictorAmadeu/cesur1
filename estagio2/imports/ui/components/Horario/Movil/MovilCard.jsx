import React from 'react';
import dayjs from '/imports/utils/dayjsConfig';

const MovilCard = ({ scheduleDay }) => {
  if (!scheduleDay || Object.keys(scheduleDay).length === 0) {
    return <p className="text-center text-gray-500">No hay datos de horario.</p>;
  }

  return (
    <div className="flex flex-col gap-4 p-4 mb-4 max-w-3xl justify-center align-center mx-auto">
      {Object.entries(scheduleDay).map(([date, info]) => {
        const dayName = dayjs(date).format('dddd');
        const dateFormatted = dayjs(date).format('DD/MM/YYYY');

        return (
          <div
            key={date}
            className="bg-white shadow-md rounded-2xl p-4 border border-gray-200 w-full"
          >
            <header className="flex justify-between items-center mb-3">
              <div>
                <h2 className="font-bold text-lg capitalize">{dayName}</h2>
                <p className="text-sm text-gray-500">{dateFormatted}</p>
              </div>
              {info.hasDay ? (
                <span className="px-3 py-1 bg-green-100 text-green-800 text-xs font-semibold rounded-full">
                  Laboral
                </span>
              ) : (
                <span className="px-3 py-1 bg-red-100 text-red-800 text-xs font-semibold rounded-full">
                  Libre
                </span>
              )}
            </header>

            {info.hasDay && info.day[0] && (
              <div className="mb-3">
                <p className="text-sm text-gray-700">
                  <strong>Horario:</strong> {info.day[0].start} - {info.day[0].end}
                </p>
              </div>
            )}

            {info.hasSegments && info.segments.length > 0 && (
              <div className="space-y-2">
                <h3 className="text-sm font-semibold text-gray-600">Segmentos:</h3>
                {info.segments.map((seg) => (
                  <div
                    key={seg.id + seg.start + seg.end}
                    className="flex justify-between bg-gray-50 p-2 rounded-lg text-sm"
                  >
                    <span>
                      {seg.start} - {seg.end}
                    </span>
                    <span className="text-gray-600 text-xs">{seg.type}</span>
                  </div>
                ))}
              </div>
            )}

            {info.hasExtraSegments && info.extraSegments.length > 0 && (
              <div className="space-y-2">
                <h3 className="text-sm font-semibold text-gray-600">Segmentos extras:</h3>
                {info.extraSegments.map((seg) => (
                  <div
                    key={seg.id + seg.start + seg.end}
                    className="flex justify-between bg-gray-50 p-2 rounded-lg text-sm"
                  >
                    <span>
                      {seg.start} - {seg.end}
                    </span>
                    <span className="text-gray-600 text-xs">{seg.type}</span>
                  </div>
                ))}
              </div>
            )}
          </div>
        );
      })}
    </div>
  );
};

export default MovilCard;
