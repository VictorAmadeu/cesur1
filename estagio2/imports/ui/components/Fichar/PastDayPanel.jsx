// components/Timer/PastDayPanel.tsx
import React from 'react';
import { useCheckin } from '/imports/provider/checkIn';
import DailyRecords from './components/DailyRecords';
import TotalTimeCalculatorForDay from './components/TotalTimeCalculatorForDay';
import { EntradaManual } from './components/EntradaManual';
import { usePermissions } from '/imports/context/permissionsContext';

const PastDayPanel = () => {
  const { timesForDay, lastTimeForDay, loadingTimes } = useCheckin();
  const { permissions } = usePermissions();

  if (loadingTimes) return <span className="w-full flex justify-center">Cargando...</span>;

  return (
    <div className="w-full flex flex-col justify-center items-center gap-4 mx-2 mb-4">
      {!loadingTimes && (
        <section className="w-full">
          {permissions.allowManual && <EntradaManual />}
          <div className="w-full flex flex-col gap-4 justify-center items-center">
            <DailyRecords timesForDay={timesForDay} />
            {lastTimeForDay && <TotalTimeCalculatorForDay lastTimeForDay={lastTimeForDay} />}
          </div>
        </section>
      )}
    </div>
  );
};

export default PastDayPanel;
