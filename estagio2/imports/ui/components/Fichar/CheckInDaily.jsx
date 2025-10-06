import React, { useEffect, useState } from 'react';
import ButtonRegister from './components/ButtonRegister';
import CheckinService from '/imports/service/checkinService';
import dayjs from '../../../utils/dayjsConfig';
import ProjectSelector from './ProjectSelector';
import { usePermissions } from '/imports/context/permissionsContext';
import useProjects from './hooks/useProjects';
import EstadoActual from './components/EstadoActual';
import TotalTimeCalculatorForDay from './components/TotalTimeCalculatorForDay';
import DailyRecords from './components/DailyRecords';
import { useCheckin } from '/imports/provider/checkIn';

const CheckInDaily = ({ home = false }) => {
  const { permissions } = usePermissions();
  const { projects, selectedProject, setSelectedProject, loadingProjects } = useProjects();
  const { timesForDay, lastTimeForDay, loadingTimes } = useCheckin();

  if (loadingTimes) return <p className="text-center">Cargando...</p>;

  return (
    <div className="w-full flex flex-col gap-4 justify-center items-center mx-2 mb-4">
      <ButtonRegister
        isEntry={lastTimeForDay ? lastTimeForDay.status : '1'}
        selectedProject={selectedProject}
      />
      {permissions.allowProjects && (
        <ProjectSelector
          permissions={permissions}
          projects={projects}
          selectedProject={selectedProject}
          setSelectedProject={setSelectedProject}
          loading={loadingProjects || loadingTimes}
          timesForDay={lastTimeForDay}
        />
      )}

      <EstadoActual timesForDay={lastTimeForDay} />

      {home && (
        <span className="w-full flex flex-col gap-4 justify-center items-center">
          <DailyRecords timesForDay={timesForDay} />
          {lastTimeForDay && <TotalTimeCalculatorForDay lastTimeForDay={lastTimeForDay} />}
        </span>
      )}
    </div>
  );
};

export default CheckInDaily;
