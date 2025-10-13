import axios from "axios";
import Cookies from "js-cookie";
import { Meteor } from "meteor/meteor";

// Crear una instancia de Axios
const axiosClient = axios.create({
  baseURL: Meteor.settings.public.baseUrl,
  timeout: 30000, // Tiempo límite para las solicitudes
});

// Agregar un interceptor para incluir el token automáticamente
axiosClient.interceptors.request.use((config) => {
  const token = Cookies.get("tokenIntranEK");
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
}, (error) => {
  return Promise.reject(error);
});

// Manejar respuestas globales
axiosClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response && error.response.status === 401) {
      console.error("Sesión expirada. Por favor, inicia sesión nuevamente.");
    }
    return Promise.reject(error);
  }
);

export default axiosClient;
