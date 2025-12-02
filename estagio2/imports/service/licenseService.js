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
 * - Consultar aprobaciones pendientes (avisos para supervisores/admin).
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
   * @param {number} documentId - id numérico del Document a borrar.
   */
  deleteDocument: async (documentId) => {
    const response = await axiosClient.post("/license/delete-file", {
      documentId,
    });
    return response.data;
  },

  /**
   * Resumen de aprobaciones pendientes para supervisores/admin.
   * No necesita parámetros.
   *
   * Respuesta esperada del backend:
   * {
   *   count: number,         // número total de pendientes
   *   hasRecords: boolean,   // true si hay al menos una
   *   list: [                // listado corto para mostrar en un modal/banner
   *     { id, userName, type, dateStart, dateEnd, status }
   *   ],
   *   code: 200
   * }
   */
  pendingSummary: async () => {
    const response = await axiosClient.post("/license/pending-summary", {});
    return response.data;
  },

  /**
   * Listado de aprobaciones pendientes (paginable/filtrable).
   *
   * @param {Object} body - parámetros opcionales de filtrado/paginación:
   *   {
   *     limit?: number,   // máximo de registros a devolver
   *     offset?: number,  // desplazamiento para paginación
   *     userId?: number,  // filtrar por usuario concreto
   *     officeId?: number // filtrar por oficina concreta
   *   }
   *
   * Respuesta esperada del backend:
   * {
   *   count: number,
   *   hasRecords: boolean,
   *   data: [ { ... } ],
   *   code: 200
   * }
   */
  pendingList: async (body = {}) => {
    const response = await axiosClient.post("/license/pending-list", body);
    return response.data;
  },
};

export default LicenseService;
