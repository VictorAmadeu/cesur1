// src/utils/dayjsConfig.js
import dayjs from "dayjs";
import utc from "dayjs/plugin/utc";
import timezone from "dayjs/plugin/timezone";
import isoWeek from "dayjs/plugin/isoWeek";
import updateLocale from "dayjs/plugin/updateLocale";
import localizedFormat from "dayjs/plugin/localizedFormat";
import localeData from "dayjs/plugin/localeData";
import advancedFormat from "dayjs/plugin/advancedFormat";
import weekOfYear from "dayjs/plugin/weekOfYear";
import isSameOrAfter from "dayjs/plugin/isSameOrAfter";
import isSameOrBefore from "dayjs/plugin/isSameOrBefore";
import customParseFormat from "dayjs/plugin/customParseFormat";
import isBetween from 'dayjs/plugin/isBetween';
import duration from "dayjs/plugin/duration";

import "dayjs/locale/es"; // Asegura que cargue 'es'

// Extiende dayjs con plugins útiles
dayjs.extend(utc);
dayjs.extend(timezone);
dayjs.extend(isoWeek);
dayjs.extend(updateLocale);
dayjs.extend(localizedFormat);
dayjs.extend(localeData);
dayjs.extend(advancedFormat);
dayjs.extend(weekOfYear);
dayjs.extend(isSameOrAfter);
dayjs.extend(isSameOrBefore);
dayjs.extend(customParseFormat);
dayjs.extend(isBetween);

// Configura la zona horaria por defecto
dayjs.tz.setDefault("Europe/Madrid");

// Establece 'es' como local por defecto
dayjs.locale("es");

// Sobrescribe algunas configuraciones del idioma español
dayjs.updateLocale("es", {
    months: "Enero_Febrero_Marzo_Abril_Mayo_Junio_Julio_Agosto_Septiembre_Octubre_Noviembre_Diciembre".split("_"),
    monthsShort: "Ene_Feb_Mar_Abr_May_Jun_Jul_Ago_Sep_Oct_Nov_Dic".split("_"),
    weekdays: "Domingo_Lunes_Martes_Miércoles_Jueves_Viernes_Sábado".split("_"),
    weekdaysShort: "Dom_Lun_Mar_Mié_Jue_Vie_Sáb".split("_"),
    weekdaysMin: "Do_Lu_Ma_Mi_Ju_Vi_Sá".split("_"),
    weekStartsOn: 1,
    firstDayOfWeek: 1,
    firstWeekContainsDate: 4,
});

export const capitalize = (str) =>
    str ? str.charAt(0).toUpperCase() + str.slice(1) : "";

export default dayjs;
