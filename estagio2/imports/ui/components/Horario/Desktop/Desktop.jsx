import React, { useEffect, useState } from 'react';
import WorkSheduleService from '/imports/service/workShedule';
import MovilCard from '../Movil/MovilCard';
import { useDate } from '/imports/provider/date';

const Desktop = () => {
  const { rangeWeek } = useDate();
  const [loading, setLoading] = useState(true);
  const [scheduleDay, setScheduleDay] = useState([]);

  useEffect(() => {
    req();
  }, [rangeWeek]);

  const req = async () => {
    try {
      setLoading(true);

      const response = await WorkSheduleService.getWorkShedule({
        startDate: rangeWeek.start,
        endDate: rangeWeek.end
      });
      setScheduleDay(response);
    } catch (error) {
      console.error(error);
    } finally {
      setLoading(false);
    }
  };

  return (
    <section aria-label="Horario diario" className="w-full">
      {loading ? (
        <p className="text-center">Cargando Horario...</p>
      ) : (
        <MovilCard scheduleDay={scheduleDay} />
      )}
    </section>
  );
};

export default Desktop;
