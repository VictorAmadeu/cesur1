import axiosClient from "./axiosClient";

/**
 * Servicio de licencias/ausencias.
 *
 * Encapsula las llamadas HTTP relacionadas con:
 * - Obtener licencias por año.
 * - Obtener una licencia concreta (con documentos).
 * - Crear una nueva licencia (con o sin adjuntos).
 * - Editar una licencia existente (fechas, comentarios, adjuntos).
 * - Eliminar un documento adjunto concreto.
 *
 * Todas las funciones devuelven directamente `response.data`.
 * Los errores de red se propagan para que el componente los gestione.
 */
const LicenseService = {
  /**
   * Obtiene las licencias del usuario autenticado filtradas por año.
   * body: { year: 2025 }
   */
  get: async (body) => {
    const response = await axiosClient.post("/license/getByYear", body);
    return response.data;
  },

  /**
   * Obtiene una licencia concreta y sus documentos adjuntos.
   * body: { id: <licenseId> }
   */
  getOne: async (body) => {
    const response = await axiosClient.post("/license/getOne", body);
    return response.data;
  },

  /**
   * Crea una nueva licencia/ausencia.
   * body puede incluir:
   * - type, comments, dateStart, dateEnd, timeStart, timeEnd
   * - files: array de arrays con objetos { name, content (base64) }
   */
  register: async (body) => {
    const response = await axiosClient.post("/license/create", body);
    return response.data;
  },

  /**
   * Edita una licencia existente.
   * body debe incluir:
   * - id: identificador de la licencia
   * - campos a modificar (comments, fechas/horas, etc.)
   * - opcional: files (nuevos adjuntos) y removedDocumentIds (ids a eliminar)
   */
  edit: async (body) => {
    const response = await axiosClient.post("/license/edit", body);
    return response.data;
  },

  /**
   * Elimina un documento adjunto concreto.
   * documentId: id numérico del Document a borrar.
   */
  deleteDocument: async (documentId) => {
    const response = await axiosClient.post("/license/delete-file", {
      documentId,
    });
    return response.data;
  },
};

export default LicenseService;
