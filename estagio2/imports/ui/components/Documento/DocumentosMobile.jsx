// Componente de documentos en móvil: lista y descarga con fetch + FileSaver

import React, { useEffect, useState } from "react";
import Cookies from "js-cookie";
import { Meteor } from "meteor/meteor";
import { callApi } from "../../../api/callApi";
import useAuthInterceptor from "../../hooks/useAuthInterceptor";
import { saveAs } from "file-saver";

// ============================================================================
// HELPERS (sin hooks - permitidos fuera del componente)
// ============================================================================

/**
 * Normaliza una URL base asegurando que termina con barra diagonal
 */
const normalizeBaseUrl = (url) => (url.endsWith("/") ? url : `${url}/`);

const apiBase = normalizeBaseUrl(Meteor.settings.public.baseUrl);

/**
 * Extrae el origen (protocolo + host) de la URL base del API
 */
const apiOrigin = (() => {
  try {
    const u = new URL(apiBase);
    return `${u.protocol}//${u.host}`;
  } catch (e) {
    return "";
  }
})();

/**
 * Construye la URL completa del archivo
 * - Si es absoluta, la retorna tal cual
 * - Si es relativa, la antepone el origen del API
 */
const buildFileUrl = (docUrl) => {
  if (!docUrl) return null;
  try {
    return new URL(docUrl).href;
  } catch (e) {
    return `${apiOrigin}${docUrl.startsWith("/") ? "" : "/"}${docUrl}`;
  }
};

// ============================================================================
// COMPONENTE PRINCIPAL
// ============================================================================

export const DocumentoMovil = () => {
  const [loading, setLoading] = useState(true);
  const [data, setData] = useState({});
  const [selectedTab, setSelectedTab] = useState(null);

  const callApiWithAuth = useAuthInterceptor(callApi);

  /**
   * Obtiene la lista de documentos del usuario desde el API
   */
  const getDocs = async () => {
    try {
      setLoading(true);
      const token = Cookies.get("tokenIntranEK");
      const response = await callApiWithAuth("document", undefined, token);
      setData(response);
      setSelectedTab(Object.keys(response)[0]);
    } catch (error) {
      console.log(error);
    } finally {
      setLoading(false);
    }
  };

  /**
   * Al montar el componente, carga los documentos
   */
  useEffect(() => {
    getDocs();
  }, []);

  /**
   * Descarga un archivo del servidor
   * 1. Intenta con token (Authorization header)
   * 2. Si falla, reintenta sin credenciales
   * 3. Marca el documento como leído
   * 4. Refresca la lista
   */
  const downloadFile = async (doc) => {
    const fileUrl = buildFileUrl(doc.url);
    if (!fileUrl) {
      alert("No se pudo construir la URL del archivo.");
      return;
    }

    try {
      const token = Cookies.get("tokenIntranEK");

      // Opciones de fetch - sin type assertions (compatible con JSX)
      const fetchOptions = {
        mode: "cors",
        ...(token && {
          headers: {
            Authorization: `Bearer ${token}`
          }
        })
      };

      let res = await fetch(fileUrl, fetchOptions);

      // Si falla con token, reintentar sin credenciales (para archivos estáticos)
      if (!res.ok && token) {
        console.warn(
          "Descarga con token falló, reintentando sin credenciales..."
        );
        res = await fetch(fileUrl, { mode: "cors" });
      }

      if (!res.ok) {
        throw new Error(`Error HTTP ${res.status}`);
      }

      const blob = await res.blob();
      saveAs(blob, doc.name);

      // Marca el documento como leído y refresca la lista
      const mark = await callApiWithAuth(
        "document/mark-read",
        { id: doc.id },
        token
      );
      if (mark?.code === "200") {
        getDocs();
      }
    } catch (error) {
      console.error("Error al descargar:", error);
      alert("No se pudo descargar el documento. Inténtalo de nuevo.");
    }
  };

  // =========================================================================
  // RENDER
  // =========================================================================

  return (
    <div>
      {loading ? (
        <div className="w-full flex justify-center">
          <span>Cargando...</span>
        </div>
      ) : (
        <div className="p-4">
          {selectedTab && data[selectedTab] && data[selectedTab].length > 0 ? (
            <>
              {/* Selector de tipo de documento */}
              <div className="mb-4">
                <select
                  className="w-full px-4 py-2 rounded-lg border border-gray-300"
                  value={selectedTab}
                  onChange={(e) => setSelectedTab(e.target.value)}
                >
                  {Object.keys(data).map((type) => (
                    <option key={type} value={type}>
                      {type}
                    </option>
                  ))}
                </select>
              </div>

              {/* Lista de documentos */}
              <div className="space-y-4">
                {data[selectedTab].map((doc) => (
                  <div
                    key={doc.id}
                    className="p-4 bg-white shadow rounded-lg border border-gray-200"
                  >
                    {/* Nombre del documento */}
                    <div className="flex justify-between items-center">
                      <span className="text-lg font-semibold text-gray-800 break-words max-w-full">
                        {doc.name}
                      </span>
                    </div>

                    {/* Información de fechas */}
                    <div className="mt-2 text-sm text-gray-600">
                      <p>
                        <strong>Creado el:</strong>{" "}
                        {doc.createdAt
                          ? new Date(doc.createdAt).toLocaleString()
                          : "N/D"}
                      </p>
                      <p>
                        <strong>Visto el:</strong>{" "}
                        {doc.viewedAt
                          ? new Date(doc.viewedAt).toLocaleString()
                          : "No visto"}
                      </p>
                    </div>

                    {/* Botón de descarga */}
                    <button
                      onClick={() => downloadFile(doc)}
                      className="mt-2 w-full px-3 py-2 text-white bg-[#3a94cc] rounded-lg hover:bg-[#3a94cc]"
                    >
                      Descargar
                    </button>
                  </div>
                ))}
              </div>
            </>
          ) : (
            <p className="text-center text-gray-600">
              No hay documentos disponibles.
            </p>
          )}
        </div>
      )}
    </div>
  );
};