import React, { useState, useEffect } from "react";
import moment from "moment";

const TotalTimeCalculator = ({ lastTimeForDay }) => {
  const [currentTime, setCurrentTime] = useState("00:00:00");
  const [lastTime, setLastTime] = useState("00:00:00");
  const [lastRegister, setLastRegister] = useState("");

  useEffect(() => {
    if (lastTimeForDay) {
      const lastTimeValue = lastTimeForDay.totalSlotTime;
      const lastRegisterValue = lastTimeForDay.hourStart;

      setLastTime(lastTimeValue);
      setLastRegister(lastRegisterValue);

      let intervalId;

      if (lastRegisterValue) {
        intervalId = setInterval(() => {
          const now = moment();
          const start = moment(lastRegisterValue);
          const diffInSeconds = now.diff(start, "seconds");
          const seconds = timeToSeconds(lastTimeValue);
          const totalSeconds = diffInSeconds + seconds;
          const duration = moment.duration(totalSeconds, "seconds");
          const formattedDuration = moment
            .utc(duration.asMilliseconds())
            .format("HH:mm:ss");

          setCurrentTime(formattedDuration);
        }, 1000);
      } else {
        setCurrentTime(lastTimeValue);
      }

      return () => clearInterval(intervalId);
    }
  }, []);

  function timeToSeconds(timeString) {
    const [hours, minutes, seconds] = timeString.split(":").map(Number);
    return hours * 3600 + minutes * 60 + seconds;
  }

  return (
    <div>
      <p>
        {" "}
        <span style={{ fontSize: "30px", marginBlock: "8px" }}>
          {currentTime}
        </span>
      </p>
    </div>
  );
};

export default TotalTimeCalculator;
