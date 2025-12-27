import { useEffect, useState } from "react";
import CheckinService from "/imports/service/checkinService";
import dayjs from "../../../../utils/dayjsConfig";
import useLoading from "./useLoading";

const useCheckinTimes = () => {
  const [timesForDay, setTimesForDay] = useState([]);
  const [lastTimeForDay, setLastTimeForDay] = useState(null);
  const { loading, triggerLoading, completeLoading } = useLoading(false);

  const fetchTimes = async () => {
    triggerLoading();
    try {
      const today = dayjs().format("YYYY-MM-DD");
      const res = await CheckinService.getByDate({ date: today });

      if (res.code === 200 && Array.isArray(res.data)) {
        setTimesForDay(res.data);
        setLastTimeForDay(res.data.length > 0 ? res.data[res.data.length - 1] : null);
      } else {
        setTimesForDay([]);
        setLastTimeForDay(null);
      }
    } catch (error) {
      console.error("Error loading times", error);
      setTimesForDay([]);
      setLastTimeForDay(null);
    } finally {
      completeLoading();
    }
  };

  useEffect(() => {
    fetchTimes();
  }, []);

  return {
    timesForDay,
    lastTimeForDay,
    loadingTimes: loading,
    refreshTimes: fetchTimes,
  };
};

export default useCheckinTimes;
