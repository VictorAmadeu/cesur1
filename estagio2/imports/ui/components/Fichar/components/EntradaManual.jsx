/**
 * Componente: EntradaManual
 * Propósito de los cambios:
 *  - Evitar registros manuales en FECHAS FUTURAS (refuerzo en UI).
 *  - Validar horas: obligatorias y que la salida sea posterior a la entrada.
 *  - Resolver advertencia de VSCode con la importación de CSS de react-toastify.
 *
 * Notas de producción:
 *  - Estos checks en frontend mejoran la UX, pero la regla debe estar duplicada en backend.
 *  - No cambiamos contratos de servicios ni rutas; sólo reforzamos validaciones y DX.
 */

import React, { useEffect, useState } from 'react';
import { useDate } from '../../../../provider/date';
import { toast } from 'react-toastify';
// VSCode/TS server a veces marca error en imports de CSS por "side-effect" en .jsx.
// Lo silenciamos de forma segura sin afectar a producción.
/* @ts-ignore */
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

export const EntradaManual = () => {
  // Fecha seleccionada en el DateProvider (puede venir de Horario, etc.)
  const { selectedDate: date } = useDate();

  // Refresca los tiempos tras registrar correctamente
  const { refreshTimes } = useCheckin();

  // Proyectos (si la empresa lo tiene habilitado)
  const {
    projects,
    loadingProjects,
    selectedProject,
    setSelectedProject,
  } = useProjects();

  // Estado local para los inputs de hora
  const [startTime, setStartTime] = useState('');
  const [endTime, setEndTime] = useState('');
  const [loading, setLoading] = useState(false);

  const { permissions } = usePermissions();
  const navigate = useNavigate();

  /**
   * Valida inputs de hora y fecha antes de enviar al backend.
   * - Obliga a indicar ambas horas.
   * - Exige que salida > entrada.
   * - Impide registrar en fecha futura.
   */
  const validateBeforeSubmit = () => {
    if (!startTime || !endTime) {
      toast.error('Debes indicar hora de entrada y salida.', {
        position: 'top-center',
      });
      return false;
    }

    // Construimos momentos con la fecha seleccionada para comparar
    const base = dayjs(date).format('YYYY-MM-DD');
    const start = dayjs(`${base}T${startTime}:00`);
    const end = dayjs(`${base}T${endTime}:00`);

    if (!end.isAfter(start)) {
      toast.error('La hora de salida debe ser posterior a la de entrada.', {
        position: 'top-center',
      });
      return false;
    }

    // Anti-futuro: comparamos a nivel de día para evitar problemas de zona horaria
    const selectedDay = dayjs(date).startOf('day');
    const today = dayjs().startOf('day');
    if (selectedDay.isAfter(today)) {
      toast.error('No se puede registrar tiempo en una fecha futura.', {
        position: 'top-center',
      });
      return false;
    }

    return true;
    };

  const setTimeToday = async () => {
    try {
      setLoading(true);

      // 1) Sesión válida
      const checkSession = await AuthService.isAuthenticated();
      if (checkSession.code !== '200') {
        navigate('/login');
        return;
      }

      // 2) Validaciones de negocio en el cliente (no sustituyen al backend)
      if (!validateBeforeSubmit()) {
        return;
      }

      const registerDeviceEnabled = permissions?.allowDeviceRegistration;
      const allowProjectsEnabled = permissions?.allowProjects;

      // 3) Construcción del payload
      const base = dayjs(date).format('YYYY-MM-DD');
      const payload = {
        hourStart: `${base}T${startTime}:00`,
        hourEnd: `${base}T${endTime}:00`,
        ...(allowProjectsEnabled && {
          project: selectedProject?.value ?? null,
        }),
      };

      // 4) Validación de dispositivo si aplica
      if (registerDeviceEnabled) {
        const deviceId = await getStoredDeviceId();
        const deviceVerified = await DeviceService.check(deviceId);
        // Si tu DeviceService devuelve objeto/HTTP, adapta esta comprobación.
        if (!deviceVerified) {
          toast.error('Dispositivo no verificado. No se puede registrar el tiempo.', {
            position: 'top-center',
          });
          return;
        }
      }

      // 5) Registrar en backend
      const setTimeForBack = await CheckInService.registerManual(payload);

      // 6) Feedback y refresco de datos
      toast.success(setTimeForBack.message, { position: 'top-center' });
      refreshTimes();
      setSelectedProject(false);
    } catch (error) {
      // Log técnico (en producción conviene enviar a un logger centralizado)
      console.error('Error en setTimeToday:', error);
      toast.error('No se pudo registrar el tiempo. Inténtalo de nuevo.', {
        position: 'top-center',
      });
    } finally {
      // 7) Reset de estados de UI
      setLoading(false);
      setStartTime('');
      setEndTime('');
    }
  };

  // Al montar, limpiamos selección de proyecto (coherencia con el flujo)
  useEffect(() => {
    setSelectedProject(false);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  return (
    <div className="ficharEntradaManual sectionCuadro">
      <section className="flex gap-4 justify-center items-center">
        <div className="text-center">
          <span>Entrada</span>
          <input
            type="time"
            value={startTime}
            onChange={(e) => setStartTime(e.target.value)}
          />
        </div>
        <div className="text-center">
          <span>Salida</span>
          <input
            type="time"
            value={endTime}
            onChange={(e) => setEndTime(e.target.value)}
          />
        </div>
      </section>

      {permissions?.allowProjects && (
        <ProjectSelector
          permissions={permissions}
          projects={projects}
          selectedProject={selectedProject}
          setSelectedProject={setSelectedProject}
          loading={loadingProjects}
          timesForDay={null}
        />
      )}

      <button disabled={loading} onClick={setTimeToday}>
        Añadir registro
      </button>
    </div>
  );
};
