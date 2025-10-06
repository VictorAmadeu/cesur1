import React from "react";
import { DatePickerSelect } from "../DatePickerSelect";
import CheckInDaily from "./CheckInDaily";
import { useDate } from "/imports/provider/date";
import PastDayPanel from "./PastDayPanel";

const IndexRegister = () => {
  const { isCurrentDay } = useDate();
  return (
    <div>
      <div className="containerPrincipal">
        <div className="desplegableFecha">
          <DatePickerSelect type={"date"} />
        </div>
        <main className="mainInformes">
          <h2 className="w-full text-center mt-4">Registros diarios</h2>
          {isCurrentDay ? <CheckInDaily home={true} /> : <PastDayPanel />}
        </main>
      </div>
    </div>
  );
};

export default IndexRegister;
