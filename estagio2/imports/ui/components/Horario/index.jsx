// imports/ui/components/Horario/index.jsx
import React, { useEffect, useState } from 'react';
import { useMediaQuery } from 'react-responsive';

// import Desktop from './Desktop/Desktop.jsx';
// import Movil from './movil/Movil.jsx';

import { usePermissions } from '../../../context/permissionsContext';
import { UnderConstruction } from '../UnderConstruction';
import { DatePickerSelect } from '../DatePickerSelect';

// Componente unificado (2.1)
import ScheduleGrid from './ScheduleGrid';

// Fecha seleccionada (provider)
import { useDate } from '../../../provider/date';

// dayjs con config compartida
import dayjs from '/imports/utils/dayjsConfig';

// Servicio real para horario
import WorkSheduleService from '/imports/service/workShedule';

/**
 * Fallback para extra segments mientras no exista el servicio real.
 * Acepta params por compatibilidad futura.
 * @param {{startDate:string, endDate:string}} _params
 * @returns {Promise<{segments:any[]}>}
 */
async function getExtraSegmentsSafe(_params) {
  try {
    // TODO: sustituir por llamada real cuando exista el servicio.
    return { segments: [] };
  } catch (e) {
    console.warn('[Horario] extraSegments fallback', e);
    return { segments: [] };
  }
}

const Horario = () => {
  const { permissions } = usePermissions();
  const isMobile = useMediaQuery({ query: '(max-width: 1024px)' }); // (no se usa para bifurcar ahora)
  const { selectedDate } = useDate();

  // Estado para ScheduleGrid
  const [loading, setLoading] = useState(false);
  const [scheduleByDate, setScheduleByDate] = useState({});
  const [extraSegments, setExtraSegments] = useState({ segments: [] });

  // Semana [start, end]
  const startDate = dayjs(selectedDate).startOf('week').format('YYYY-MM-DD');
  const endDate = dayjs(startDate).add(6, 'day').format('YYYY-MM-DD');

  useEffect(() => {
    let alive = true;

    async function load() {
      try {
        setLoading(true);

        // 1) Horario semanal
        const scheduleResp = await WorkSheduleService.getWorkShedule({
          startDate,
          endDate,
        });
        if (alive) setScheduleByDate(scheduleResp || {});

        // 2) Extras (fallback controlado)
        const extraResp = await getExtraSegmentsSafe({ startDate, endDate });
        if (alive) setExtraSegments(extraResp || { segments: [] });
      } catch (e) {
        console.error('[Horario] load error', e);
        if (alive) {
          setScheduleByDate({});
          setExtraSegments({ segments: [] });
        }
      } finally {
        if (alive) setLoading(false);
      }
    }

    load();
    return () => {
      alive = false;
    };
  }, [startDate, endDate]);

  if (!permissions.allowWorkSchedule) {
    return (
      <section>
        <UnderConstruction section="Horario" />
      </section>
    );
  }

  return (
    <section>
      <header className="desplegableFecha mb-4">
        <DatePickerSelect type="week" allowFutureDates={true} />
      </header>

      {loading ? (
        <p className="text-center">Cargando horario...</p>
      ) : (
        <ScheduleGrid
          startDate={startDate}
          endDate={endDate}
          scheduleByDate={scheduleByDate}
          extraSegments={extraSegments}
        />
      )}
    </section>
  );
};

export default Horario;
