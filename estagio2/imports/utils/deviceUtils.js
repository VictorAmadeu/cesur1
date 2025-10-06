import Cookies from "js-cookie";
import localforage from "localforage";

export const getStoredDeviceId = async () => {
  const sources = [
    { source: "localforage", value: await localforage.getItem("deviceId") },
    { source: "cookies", value: Cookies.get("deviceId_backup") },
    { source: "localStorage", value: localStorage.getItem("deviceId_backup") },
    { source: "sessionStorage", value: sessionStorage.getItem("deviceId_backup") },
  ];

  const found = sources.find((item) => item.value);

  if (found) {
    return { code: 200, status: "success", deviceId: found.value, source: found.source };
  }

  return { code: 404, status: "error", message: "Device ID no encontrado en ningún almacenamiento", deviceId: null };
};
