import React, { useEffect, useState } from 'react';
import { useDate } from '../../../../provider/date';
import { toast } from 'react-toastify';
import 'react-toastify/dist/ReactToastify.css';
import ProjectSelector from '../ProjectSelector';
import CheckInService from '/imports/service/checkinService.js';
import AuthService from '/imports/service/authService.js';
import dayjs from 'dayjs';
import DeviceService from '/imports/service/deviceService';
import { getStoredDeviceId } from '/imports/utils/deviceUtils';
import { usePermissions } from '../../../../context/permissionsContext';
import { useNavigate } from 'react-router-dom';
import useProjects from '../hooks/useProjects';
import { useCheckin } from '/imports/provider/checkIn';
import { set } from 'date-fns';

export const EntradaManual = () => {
  const { selectedDate: date } = useDate();
  const { refreshTimes } = useCheckin();
  const { projects, loadingProjects, selectedProject, setSelectedProject } = useProjects();
  const [startTime, setStartTime] = useState('');
  const [endTime, setEndTime] = useState('');
  const [loading, setLoading] = useState(false);
  const { permissions } = usePermissions();
  const navigate = useNavigate();

  const setTimeToday = async () => {
    try {
      setLoading(true);
      if (startTime === endTime) {
        toast.error(
          'La diferencia entre la hora de inicio y la hora de finalización debe ser de al menos 1 minuto.',
          { position: 'top-center' }
        );
        return;
      }

      const checkSession = await AuthService.isAuthenticated();
      if (checkSession.code !== '200') {
        navigate('/login');
        return;
      }

      const registerDeviceEnabled = permissions.allowDeviceRegistration;
      const allowProjectsEnabled = permissions.allowProjects;

      // Construcción del payload según si los proyectos están habilitados o no
      const payload = {
        hourStart: `${dayjs(date).format('YYYY-MM-DD')}T${startTime}:00`,
        hourEnd: `${dayjs(date).format('YYYY-MM-DD')}T${endTime}:00`,
        ...(allowProjectsEnabled && {
          project: selectedProject?.value ? selectedProject?.value : null
        }) // Solo agrega `project` si está habilitado
      };

      if (registerDeviceEnabled) {
        // Si el registro por dispositivo está habilitado, primero verificamos el dispositivo
        const devideId = await getStoredDeviceId();
        const deviceVerified = await DeviceService.check(devideId); // Función ficticia para ilustrar la verificación
        if (!deviceVerified) {
          toast.error('Dispositivo no verificado. No se puede registrar el tiempo.', {
            position: 'top-center'
          });
          return;
        }
      }

      // Registrar el tiempo
      const setTimeForBack = await CheckInService.registerManual(payload);
      toast.success(setTimeForBack.message, { position: 'top-center' });
      refreshTimes();
      setSelectedProject(false);
    } catch (error) {
      console.error('Error en setTimeToday:', error);
    } finally {
      setLoading(false);
      setStartTime('');
      setEndTime('');
    }
  };

  useEffect(() => {
    setSelectedProject(false);
  }, []);

  return (
    <div className="ficharEntradaManual sectionCuadro">
      <section className="flex gap-4 justify-center items-center">
        <div className="text-center">
          <span>Entrada</span>
          <input type="time" value={startTime} onChange={(e) => setStartTime(e.target.value)} />
        </div>
        <div className="text-center">
          <span>Salida</span>
          <input type="time" value={endTime} onChange={(e) => setEndTime(e.target.value)} />
        </div>
      </section>
      {permissions.allowProjects && (
        <ProjectSelector
          permissions={permissions}
          projects={projects}
          selectedProject={selectedProject}
          setSelectedProject={setSelectedProject}
          loading={loadingProjects}
          timesForDay={null}
        />
      )}
      <button disabled={loading} onClick={() => setTimeToday()}>
        Añadir registro
      </button>
    </div>
  );
};
