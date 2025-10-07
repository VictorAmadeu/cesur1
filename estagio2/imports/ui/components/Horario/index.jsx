// imports/ui/components/Horario/index.jsx
import React, { useEffect } from 'react';
import { useMediaQuery } from 'react-responsive';
import Desktop from './Desktop/Desktop.jsx';
import Movil from './movil/Movil.jsx';
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
          {isMobile ? <Movil /> : <Desktop />}
        </>
      ) : (
        <UnderConstruction section="Horario" />
      )}
    </section>
  );
};

export default Horario;
