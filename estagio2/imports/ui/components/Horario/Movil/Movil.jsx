import React, { useEffect } from 'react';
import { DatePickerSelect } from '../../DatePickerSelect';
// ⚠️ Se mantiene este import porque se usa para getWorkShedule.
// Ojo al nombre del archivo/servicio (workShedule). Si en algún momento
// el repo se pasa a un FS case-sensitive, conviene alinear a "WorkSchedule".
import WorkSheduleService from '/imports/service/workShedule';
import { useDate } from '../../../../provider/date';

const Movil = () => {
  const { selectedDate } = useDate();
  const [loading, setLoading] = React.useState(false);
  const [scheduleByDate, setScheduleByDate] = React.useState([]);
  // En móvil trabajaremos con un ARRAY de segmentos extra (normalizado).
  const [extraSegments, setExtraSegments] = React.useState([]);

  // ✅ Fallback para extra segments mientras no exista el servicio real.
  // Acepta params para que la llamada con objeto no falle por tipos.
  // No usamos el parámetro (por ahora), por eso el guion bajo.
  async function getExtraSegmentsSafe(_params) {
    try {
      // TODO: sustituir por llamada real cuando dispongamos del servicio.
      return { segments: [] };
    } catch (e) {
      console.warn('[Horario/Móvil] extraSegments fallback', e);
      return { segments: [] };
    }
  }

  useEffect(() => {
    const params = { startDate: selectedDate, endDate: selectedDate };
    req(params);
    reqExtra(params);
  }, [selectedDate]);

  const req = async ({ startDate, endDate }) => {
    try {
      setLoading(true);
      const response = await WorkSheduleService.getWorkShedule({
        startDate,
        endDate
      });
      // Mantengo el set tal cual lo tenías para no cambiar contratos.
      setScheduleByDate(response);
    } catch (e) {
      console.error('[Horario/Móvil] getWorkShedule error', e);
    } finally {
      setLoading(false);
    }
  };

  const reqExtra = async ({ startDate, endDate }) => {
    try {
      setLoading(true);
      // ❌ Llamada que rompía:
      // const response = await WorkSheduleService.checkExtraSegment({ startDate, endDate });

      // ✅ Fallback seguro:
      const response = await getExtraSegmentsSafe({ startDate, endDate });

      // Normalizamos a ARRAY de segmentos, sea cual sea el shape devuelto.
      const list = Array.isArray(response?.segments)
        ? response.segments
        : Array.isArray(response?.data?.segments)
          ? response.data.segments
          : [];

      setExtraSegments(list);
    } catch (e) {
      console.error('[Horario/Móvil] extraSegments error', e);
    } finally {
      setLoading(false);
    }
  };

  return (
    <section aria-label="Horario diario">
      <header className="mb-4">
        <DatePickerSelect type="date" allowFutureDates={true} />
      </header>
      {loading ? (
        <p className="text-center">Cargando Horario...</p>
      ) : (
        <>
          {/* Aquí podrás usar `scheduleByDate` y `extraSegments` cuando pintes la UI móvil */}
          Movil
        </>
      )}
    </section>
  );
};

export default Movil;
