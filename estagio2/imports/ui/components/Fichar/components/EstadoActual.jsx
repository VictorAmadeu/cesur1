import React from 'react';
import TotalTimeCalculator from './TotalTimeCalculator';
import dayjs from '../../../../utils/dayjsConfig';

const EstadoActual = ({ timesForDay }) => {
  return (
    <div
      id="actualState"
      className="sectionCuadro w-full flex flex-col justify-center items-center"
    >
      <h2>
        <i className="fa-solid fa-battery-three-quarters mt-2"></i> Estado actual
      </h2>

      {!timesForDay ? (
        <h3 className="mt-2">Recargando las pilas</h3>
      ) : timesForDay.status === 0 ? (
        <>
          <h3>
            A pleno rendimiento desde las{' '}
            {timesForDay.hourStart ? dayjs(timesForDay.hourStart).format('HH:mm') : null}
          </h3>
          <TotalTimeCalculator lastTimeForDay={timesForDay} />
        </>
      ) : (
        <h3 className="mt-2">Recargando las pilas</h3>
      )}
    </div>
  );
};

export default EstadoActual;
