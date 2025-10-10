import axios from "axios";
import Cookies from "js-cookie";
import { Meteor } from "meteor/meteor";

// Instancia de Axios con baseURL desde settings (debe incluir el /api)
const axiosClient = axios.create({
  baseURL: Meteor.settings?.public?.baseUrl,
  timeout: 30000,
});

// Interceptor: añade JWT si existe
axiosClient.interceptors.request.use(
  (config) => {
    const token = Cookies.get("tokenIntranEK");
    if (token) {
      // NOTA: no forzamos Content-Type aquí; axios la ajusta según el body (JSON vs FormData)
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => Promise.reject(error)
);

// Interceptor: manejo global de respuestas/errores
axiosClient.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error?.response?.status;

    if (status === 401) {
      // Sesión expirada o token inválido: limpiamos y redirigimos al login
      try {
        Cookies.remove("tokenIntranEK");
      } catch (_) {}
      if (typeof window !== "undefined" && window.location.pathname !== "/login") {
        window.location.assign("/login");
      }
    } else if (!error.response) {
      // Error de red (timeout, CORS, desconexión)
      console.error("Error de red o tiempo de espera agotado.");
    }

    return Promise.reject(error);
  }
);

export default axiosClient;
