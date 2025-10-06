import React from 'react';

const TotalTimeCalculatorForDay = ({ lastTimeForDay }) => {
  return (
    <div>
      <p>
        {' '}
        <span style={{ fontSize: '30px', marginBlock: '8px' }}>
          {lastTimeForDay.totalTime ?? '00:00:00'}
        </span>
      </p>
    </div>
  );
};

export default TotalTimeCalculatorForDay;
