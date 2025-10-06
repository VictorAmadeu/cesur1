import moment from "moment";

const checkDeviceRegistration = async () => {
  try {
    const token = Cookies.get("tokenIntranEK");
    const deviceId = await localforage.getItem('deviceId');
    const response = await callApi("device/check-registration", { deviceId }, token);
    return response
  } catch (error) {
    console.error("Error al verificar el registro del dispositivo:", error);
    return false;
  }
};

// Función para generar la grilla del mes
export const generateMonthGrid = (date) => {
  const firstDayOfMonth = moment(date).clone().startOf("month");
  let startOfWeek = firstDayOfMonth.clone().startOf("week").day(1); // Establecer el primer día de la semana como lunes

  const endOfWeek = firstDayOfMonth.clone().endOf("month").endOf("week").day(0); // Establecer el último día de la semana como domingo

  const monthGrid = [];
  let startDay = startOfWeek.clone();

  while (startDay.isBefore(endOfWeek)) {
    const week = [];
    for (let i = 0; i < 7; i++) {
      week.push(startDay.clone());
      startDay.add(1, "day");
    }
    monthGrid.push(week);
  }

  return monthGrid;
};

// Función para obtener los segundos a partir de una cadena en formato HH:MM:SS
const getSecondsFromHHMMSS = (timeString) => {
  const [hours, minutes, seconds] = timeString.split(":").map(Number);
  return hours * 3600 + minutes * 60 + seconds;
};

// Función para agrupar las entradas por día
export const groupEntriesByDay = (entries) => {
  const groupedEntries = {};

  if (time !== null) {
    // Iterar sobre todas las entradas
    entries.forEach((entry) => {
      // Extraer la parte de la cadena de la hora (desde el índice 11 al 18)
      const timeSubstring = entry.totalTime.slice(11, 19);

      // Formatear la hora extraída como HH:MM:SS
      const formattedTime = moment(timeSubstring, "HH:mm:ss").format(
        "HH:mm:ss"
      );

      // Actualizar la propiedad totalTime con la hora formateada
      entry.totalTime = formattedTime;

      // Obtener la fecha del createdAt
      const day = moment(entry.createdAt).format("YYYY-MM-DD");

      // Si no existe un grupo para ese día, crearlo
      if (!groupedEntries[day]) {
        groupedEntries[day] = {
          entries: [],
          totalDuration: 0, // Inicializar la duración total del día en 0
        };
      }

      // Sumar el totalTime al totalDuration del día
      groupedEntries[day].totalDuration += getSecondsFromHHMMSS(formattedTime);

      // Agregar la entrada al grupo correspondiente
      groupedEntries[day].entries.push(entry);
    });
  }

  return groupedEntries;
};

export const createImage = (url) =>
  new Promise((resolve, reject) => {
    const image = new Image();
    image.addEventListener('load', () => resolve(image));
    image.addEventListener('error', (error) => reject(error));
    image.setAttribute('crossOrigin', 'anonymous'); // para evitar problemas de CORS
    image.src = url;
  });

export const getRadianAngle = (degreeValue) => (degreeValue * Math.PI) / 180;

export const MONTHS = [
  "Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio",
  "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"
];

export const ROLES = [
  "ROLE_SUPER_ADMIN",
  "ROLE_ADMIN",
  "ROLE_SUPERVISOR",
]