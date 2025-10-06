import React, { useEffect } from "react";
import { DatePickerSelect } from "../../DatePickerSelect";
import WorkSheduleService from "/imports/service/workShedule";
import { useDate } from "../../../../provider/date";

const Movil = () => {
  const { selectedDate } = useDate();
  const [loading, setLoading] = React.useState(false);
  const [scheduleByDate, setScheduleByDate] = React.useState([]);
  const [extraSegments, setExtraSegments] = React.useState([]);

  useEffect(() => {
    req({
      startDate: selectedDate,
      endDate: selectedDate
    })
    reqExtra({
      startDate: selectedDate,
      endDate: selectedDate
    })
  }, [selectedDate])

  const req = async ({ startDate, endDate }) => {
    try {
      setLoading(true);
      const response = await WorkSheduleService.getWorkShedule({
        startDate,
        endDate
      })
      console.log(response)
      setScheduleByDate(response);
      setLoading(false);
    } catch (e) {
      console.log(e)
      setLoading(false);
    }
  }

  const reqExtra = async ({ startDate, endDate }) => {
    try {
      setLoading(true);
      console.log("reqExtra", startDate, endDate)
      const response = await WorkSheduleService.checkExtraSegment({
        startDate: startDate,
        endDate: endDate
      })
      console.log("resExtra", response)
      setExtraSegments(response);
      setLoading(false);
    } catch (e) {
      console.log(e)
      setLoading(false);
    }
  }

  return (
    <section aria-label="Horario diario">
      <header className="mb-4">
        <DatePickerSelect type="date" allowFutureDates={true} />
      </header>
      {loading ? (
        <p className="text-center">Cargando Horario...</p>
      ) : (
        <>
          Movil
        </>
      )}
    </section>
  );
};

export default Movil;
