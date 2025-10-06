import React from "react";
import { RoutesApp } from "./routes/index";
import moment from "moment";
import "moment/locale/es";
import "moment-timezone";
import { RegisterProvider } from "../provider/register";
import { DateProvider } from "../provider/date";
import { PermissionsProvider } from "../context/permissionsContext";
import { CheckinProvider } from "../provider/checkIn";

moment.locale("es");
moment.tz.setDefault("Europe/Madrid");

export const App = () => {
  return (
    <div className="app">
      <PermissionsProvider>
        <DateProvider>
          <CheckinProvider>
            <RegisterProvider>
              <RoutesApp />
            </RegisterProvider>
          </CheckinProvider>
        </DateProvider>
      </PermissionsProvider>
    </div>
  );
};
