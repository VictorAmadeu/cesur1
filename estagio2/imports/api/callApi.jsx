// C:\Proyectos\intranek\imports\api\callApi.jsx
// ------------------------------------------------------
// Cliente ligero para enviar peticiones al backend (Symfony)
// desde la app Intranek (Web y Mobile).
// - Normaliza la URL base para evitar errores como "/apidocument".
// - Simplifica las opciones de fetch para evitar problemas de tipado
//   y mantener el código más mantenible.
// ------------------------------------------------------

import { Meteor } from "meteor/meteor";

// ------------------------------------------------------
// Función auxiliar: se asegura de que la baseUrl SIEMPRE
// termine en "/".
//   - Si ya termina en "/", la deja igual.
//   - Si no, añade la "/" al final.
// Esto protege frente a errores de configuración en los
// distintos settings (development, mobile, etc.).
// ------------------------------------------------------
const normalizeBaseUrl = (url) => (url.endsWith("/") ? url : `${url}/`);

// Leemos la baseUrl de la configuración de Meteor
// y la normalizamos para que siempre tenga la "/" final.
const baseUrl = normalizeBaseUrl(Meteor.settings.public.baseUrl);

/**
 * Envía una petición POST al backend.
 *
 * @param {string} url - URL completa (baseUrl + endpoint).
 * @param {object|undefined} data - Cuerpo JSON a enviar (puede ser undefined).
 * @param {string|undefined} token - Token JWT opcional para Authorization.
 * @returns {Promise<object>} - Respuesta JSON parseada o {} si no hay cuerpo.
 */
const sendRequest = async (url, data, token) => {
  // Cabeceras: siempre enviamos JSON y, si existe token,
  // añadimos la cabecera Authorization.
  const headers = new Headers({
    "Content-Type": "application/json",
    ...(token && { Authorization: `Bearer ${token}` }),
  });

  // Opciones de fetch simplificadas:
  // - method: "POST"
  // - headers: cabeceras definidas arriba
  // - body: solo se envía si hay datos (evitamos JSON.stringify(undefined))
  const requestOptions = {
    method: "POST",
    headers,
    body: data ? JSON.stringify(data) : undefined,
  };

  try {
    const response = await fetch(url, requestOptions);
    const text = await response.text(); // leemos como texto para controlar vacíos
    return text ? JSON.parse(text) : {}; // si hay texto, parseamos JSON; si no, devolvemos {}
  } catch (error) {
    console.error("Error en sendRequest:", error);
    throw error; // dejamos que el llamador decida qué hacer con el error
  }
};

/**
 * Llama a un endpoint del backend concatenando:
 *   baseUrl (ya normalizada) + point (endpoint).
 *
 * Ejemplos:
 *   callApi("document", {...}, token)
 *   callApi("reset-password/request", {...}, undefined)
 */
export const callApi = async (point, param, token) => {
  // Como baseUrl termina en "/", las URLs quedan:
  //   "http://10.0.2.2:8000/api/" + "document"
  //   → "http://10.0.2.2:8000/api/document"
  const url = baseUrl + point;

  // Ya no envolvemos en try/catch porque sendRequest
  // ya gestiona el error y lo relanza.
  return sendRequest(url, param, token);
};

/**
 * Variante de callApi pensada para "mantener vivo" el backend
 * (pings, renovaciones de sesión, etc.).
 * - Si hay error, se registra en consola y se devuelve null.
 */
export const keepAlive = async (point, param, token) => {
  const url = baseUrl + point;

  try {
    return await sendRequest(url, param, token);
  } catch (error) {
    console.error("Error en keepAlive:", error);
    return null; // No rompemos el flujo de la app en caso de fallo
  }
};

/**
 * Envía un formulario multipart/form-data al backend.
 *
 * @param {string} point - Endpoint (por ejemplo "profile/upload-photo").
 * @param {FormData} formData - Objeto FormData con los campos/archivos.
 * @param {string|undefined} token - Token JWT opcional.
 * @returns {Promise<object>} - Respuesta JSON del backend.
 */
export const sendFormData = async (point, formData, token) => {
  const url = baseUrl + point;

  // Solo añadimos Authorization si existe token.
  const headers = new Headers({
    ...(token && { Authorization: `Bearer ${token}` }),
  });

  const requestOptions = {
    method: "POST",
    headers,
    body: formData, // NO se convierte a JSON: el navegador se encarga del boundary
  };

  try {
    const response = await fetch(url, requestOptions);
    return response.json();
  } catch (error) {
    console.error("Error en sendFormData:", error);
    throw error;
  }
};
