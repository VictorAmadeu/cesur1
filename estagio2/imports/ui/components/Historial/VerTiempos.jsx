import React, { useEffect, useState } from 'react';
import moment from 'moment';
import { DatePickerSelect } from '../DatePickerSelect';
import { useMediaQuery } from 'react-responsive';
import 'moment/locale/es';
import MonthGrid from './MonthGrid';
import { useDate } from '../../../provider/date';
import DayList from './DayList';
import { useNavigate } from 'react-router-dom';
import { toast } from 'react-toastify';
import 'react-toastify/dist/ReactToastify.css';
import CheckinService from '/imports/service/checkinService';
import dayjs from 'dayjs';
import { MONTHS } from '/imports/utils';

moment.locale('es');

const VerTimpos = () => {
  const navigate = useNavigate();
  const [time, setTime] = useState(null);
  const [totalTime, setTotalTime] = useState('');
  const [month, setMonth] = useState(null);
  const { selectedDate: date } = useDate();
  const [loading, setLoading] = useState(true);
  const isMobile = useMediaQuery({ query: '(max-width: 768px)' });

  const getTime = async () => {
    setLoading(true);
    const primerDiaDelMes = moment(date).startOf('month').format('YYYY-MM-DD');
    const ultimoDiaDelMes = moment(date).endOf('month').format('YYYY-MM-DD');
    const rAll = await CheckinService.getByDates({
      startDate: primerDiaDelMes,
      endDate: ultimoDiaDelMes
    });
    if (rAll.code !== 200) {
      toast.error(rAll.message);
      navigate('/login');
      setLoading(false);
    }
    setTime(rAll.data);
    setTotalTime(rAll.totalTime);

    const monthNumber = dayjs(date).month();
    const monthName = MONTHS[monthNumber];
    setMonth(monthName);
    setLoading(false);
  };

  useEffect(() => {
    getTime();
  }, [date]);

  return (
    <div>
      <div>
        <div style={{ textAlign: 'center', margin: '8px' }}>
          <div className="desplegableFecha">
            <DatePickerSelect type={'month'} />
          </div>
          {loading ? (
            <div className="w-full text-center">Cargando...</div>
          ) : (
            <main className="mainInformes">
              <h1>Registros de {month ?? ''}</h1>
              <div className="month-grid-container">
                {isMobile ? (
                  <div
                    style={{
                      width: '100%',
                      marginTop: '30px'
                    }}
                  >
                    <DayList time={time} />
                  </div>
                ) : (
                  <div style={{ width: '100%', marginTop: '30px' }}>
                    <MonthGrid time={time} />
                  </div>
                )}
                <div className="w-full py-4 text-lg flex gap-2 justify-center items-center">
                  <p className="font-semibold">Tiempo total:</p>
                  <p>{totalTime ?? '00:00'}</p>
                </div>
              </div>
            </main>
          )}
        </div>
      </div>
    </div>
  );
};

export default VerTimpos;
