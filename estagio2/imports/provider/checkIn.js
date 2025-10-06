// CheckinProvider.jsx
import React, { createContext, useContext, useEffect, useState } from 'react';
import CheckinService from '/imports/service/checkinService';
import { useDate } from './date';
import dayjs from '../utils/dayjsConfig.js';

const CheckinContext = createContext(null);

export const useCheckin = () => useContext(CheckinContext);

export const CheckinProvider = ({ children }) => {
  const { selectedDate } = useDate();
  const [timesForDay, setTimesForDay] = useState([]);
  const [lastTimeForDay, setLastTimeForDay] = useState(null);
  const [loadingTimes, setLoadingTimes] = useState(false);

  const refreshTimes = async () => {
    setLoadingTimes(true);
    const formattedDate = dayjs(selectedDate).format('YYYY-MM-DD');
    try {
      const res = await CheckinService.getByDate({ date: formattedDate });

      if (res.code === 200 && Array.isArray(res.data)) {
        setTimesForDay(res.data);
        setLastTimeForDay(res.data.length > 0 ? res.data[res.data.length - 1] : null);
      } else {
        setTimesForDay([]);
        setLastTimeForDay(null);
      }
    } catch (error) {
      console.error('Error loading times', error);
      setTimesForDay([]);
      setLastTimeForDay(null);
    } finally {
      setLoadingTimes(false);
    }
  };

  useEffect(() => {
    refreshTimes();
  }, [selectedDate]);

  const value = {
    timesForDay,
    lastTimeForDay,
    loadingTimes,
    refreshTimes
  };

  return <CheckinContext.Provider value={value}>{children}</CheckinContext.Provider>;
};
