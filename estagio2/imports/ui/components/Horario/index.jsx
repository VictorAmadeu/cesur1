import React, { useEffect } from 'react';
import { useMediaQuery } from 'react-responsive';
import Desktop from './desktop/Desktop';
import Movil from './movil/Movil';
import { usePermissions } from '../../../context/permissionsContext';
import { UnderConstruction } from '../UnderConstruction';
import { DatePickerSelect } from '../DatePickerSelect';

const Horario = () => {
  const { permissions } = usePermissions();
  const isMobile = useMediaQuery({ query: '(max-width: 1024px)' });

  return (
    <section>
      {permissions.allowWorkSchedule ? (
        <>
          <header className="desplegableFecha mb-4">
            <DatePickerSelect type="week" allowFutureDates={true} />
          </header>
          <Movil />
        </>
      ) : (
        <UnderConstruction section="Horario" />
      )}
    </section>
  );
};

export default Horario;
