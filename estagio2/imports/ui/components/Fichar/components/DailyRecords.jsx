import { Clock, LogIn, LogOut } from "lucide-react";
import React from "react";

const RecordItem = ({ record }) => (
  <div className="bg-gray-100 mb-2 h-12 rounded-lg w-full flex flex-row gap-4 items-center justify-center">
    <div className="flex items-center justify-center flex-1 min-w-0">
      <LogIn className="mr-2 flex-shrink-0" size={20} color="green" />
      <span className="text-base truncate">
        {record.hourStart.slice(11, 16)}
      </span>
    </div>
    <div className="flex items-center justify-center flex-1 min-w-0">
      <LogOut className="mr-2 flex-shrink-0" size={20} color="red" />
      <span className="text-base truncate">{record.hourEnd.slice(11, 16)}</span>
    </div>
    <div className="flex items-center justify-center flex-1 min-w-0">
      <Clock className="mr-2 flex-shrink-0" size={20} />
      <span className="text-base truncate">{record.totalSlotTime}</span>
    </div>
    {record.project && (
      <div className="flex items-center justify-center flex-1 min-w-0">
        <span className="text-base font-medium"></span>
        <span className="ml-2 text-base">{record.project}</span>
      </div>
    )}
  </div>
);

const RecordsSection = ({ records, title }) => (
  <div className="w-full">
    <h4 className="font-semibold text-lg mb-2">{title}</h4>
    <div className="w-full flex flex-col mx-auto">
      {records.map((record, index) => (
        <RecordItem key={index} record={record} />
      ))}
    </div>
  </div>
);

const DailyRecords = ({ timesForDay }) => {
  // Filtrar primero los registros válidos
  const validRecords = Array.isArray(timesForDay)
    ? timesForDay.filter((record) => record.status !== 0)
    : [];

  // Luego separarlos en mañana y tarde
  const morningRecords = [];
  const afternoonRecords = [];
  validRecords.forEach((record) => {
    const hourStart = parseInt(record.hourStart.slice(11, 13));
    if (hourStart >= 0 && hourStart < 14) {
      morningRecords.push(record);
    } else {
      afternoonRecords.push(record);
    }
  });

  return (
    <div className="w-full flex flex-col items-center justify-center gap-2">
      <>
        {morningRecords.length > 0 || afternoonRecords.length > 0 ? (
          <>
            {morningRecords.length > 0 && (
              <RecordsSection
                records={morningRecords}
                title="Registros de la mañana:"
              />
            )}

            {afternoonRecords.length > 0 && (
              <RecordsSection
                records={afternoonRecords}
                title="Registros de la tarde:"
              />
            )}
          </>
        ) : (
          <p>No hay registros para este día</p>
        )}
      </>
    </div>
  );
};

export default DailyRecords;
