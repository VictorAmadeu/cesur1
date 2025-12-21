// C:\Proyectos\intranek\imports\ui\components\Documento\DocumentosWeb.jsx
//
// Versi├│n web unificada con la versi├│n m├│vil para la descarga de documentos.
// - Usa file-saver para forzar la descarga (mejor que data URI).
// - Convierte base64 ΓåÆ Blob con la utilidad base64ToBlob.
// - Marca el documento como le├¡do antes de descargar.
// - Manejo de errores no bloqueante para no romper la app.
//
// Requisitos:
//   npm i file-saver
//   Tener la utilidad en imports/utils/files.js

import React, { useEffect, useState } from "react";
import Cookies from "js-cookie";
import { callApi } from "../../../api/callApi";
import useAuthInterceptor from "../../hooks/useAuthInterceptor";
import { saveAs } from "file-saver";                 // fuerza la descarga
import { base64ToBlob } from "../../../utils/files"; // util base64 ΓåÆ Blob (ruta: imports/utils/files.js)

export const DocumentosWeb = () => {
  const [loading, setLoading] = useState(true);
  const [data, setData] = useState({});                // { "N├│minas": [...], "Contratos": [...] }
  const [selectedTab, setSelectedTab] = useState(null);

  // Cliente API con interceptor de autorizaci├│n/401
  const callApiWithAuth = useAuthInterceptor(callApi);

  // Cargar documentos agrupados por tipo
  const getDocs = async () => {
    try {
      setLoading(true);
      const token = Cookies.get("tokenIntranEK");
      const response = await callApiWithAuth("document", undefined, token);
      setData(response || {});
      const firstKey =
        response && Object.keys(response).length > 0
          ? Object.keys(response)[0]
          : null;
      setSelectedTab(firstKey);
    } catch (error) {
      console.error("Error obteniendo documentos:", error);
      setData({});
      setSelectedTab(null);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    getDocs();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  /**
   * Descarga un documento usando su base64.
   * 1) Marca el documento como le├¡do en backend (si falla, no bloquea).
   * 2) Detecta tipo MIME seg├║n extensi├│n.
   * 3) Convierte base64 ΓåÆ Blob y descarga con file-saver (mejor en m├│vil y escritorio).
   */
  const downloadBase64File = async (base64, fileName, id) => {
    // 1) Marcar como le├¡do (errores no bloquean la descarga)
    try {
      const token = Cookies.get("tokenIntranEK");
      const req = await callApiWithAuth("document/mark-read", { id }, token);
      if (req?.code === "200") {
        getDocs(); // refresca viewedAt
      }
    } catch (error) {
      console.warn("No se pudo marcar como le├¡do:", error);
    }

    // 2) Deducir MIME por extensi├│n (fallback seguro)
    const ext = (fileName?.split(".").pop() || "").toLowerCase();
    let mimeType = "application/octet-stream";
    if (ext === "pdf") mimeType = "application/pdf";
    else if (ext === "jpg" || ext === "jpeg") mimeType = "image/jpeg";
    else if (ext === "png") mimeType = "image/png";
    else if (ext === "gif") mimeType = "image/gif";
    else if (ext === "csv") mimeType = "text/csv";
    else if (ext === "xlsx")
      mimeType =
        "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet";

    // 3) Convertir y descargar (con fallback por si algo falla)
    try {
      const blob = base64ToBlob(base64, mimeType); // admite "data:...;base64,AAA" o "AAA"
      saveAs(blob, fileName || "documento");
    } catch (e) {
      try {
        const link = document.createElement("a");
        link.href = base64.startsWith("data:")
          ? base64
          : `data:${mimeType};base64,${base64}`;
        link.download = fileName || "documento";
        document.body.appendChild(link);
        link.click();
        link.remove();
      } catch (err) {
        alert("No se pudo iniciar la descarga del documento.");
        console.error("Fallo al descargar documento:", err);
      }
    }
  };

  return (
    <div>
      {loading ? (
        <div className="w-full flex justify-center">
          <p>Cargando...</p>
        </div>
      ) : (
        <div className="p-6 max-w-4xl mx-auto">
          {/* Pesta├▒as por tipo */}
          <div className="flex justify-start flex-wrap gap-2 mb-4">
            {Object.keys(data).map((type) => (
              <button
                key={type}
                onClick={() => setSelectedTab(type)}
                className={`px-4 py-2 text-sm font-medium rounded
                          ${
                            selectedTab === type
                              ? "bg-[#3a94cc] text-white"
                              : "bg-gray-200 text-gray-700 hover:bg-gray-300"
                          }`}
              >
                {type}
              </button>
            ))}
          </div>

          {/* Tabla de documentos */}
          <div className="w-full overflow-x-auto">
            {selectedTab &&
            data[selectedTab] &&
            data[selectedTab].length > 0 ? (
              <table className="min-w-full bg-white border border-gray-200 rounded-lg">
                <thead>
                  <tr className="bg-[#3a94cc]">
                    <th className="text-left py-3 px-4 font-semibold text-white text-sm">
                      Nombre
                    </th>
                    <th className="text-left py-3 px-4 font-semibold text-white text-sm">
                      Fecha de Creaci├│n
                    </th>
                    <th className="text-left py-3 px-4 font-semibold text-white text-sm">
                      Fecha de Visualizaci├│n
                    </th>
                    <th className="text-left py-3 px-4 font-semibold text-white text-sm">
                      Acciones
                    </th>
                  </tr>
                </thead>
                <tbody>
                  {data[selectedTab].map((doc) => (
                    <tr className="hover:bg-gray-50" key={doc.id}>
                      <td className="py-2 px-4">{doc.name}</td>
                      <td className="py-3 px-4">
                        {doc.createdAt
                          ? new Date(doc.createdAt).toLocaleString()
                          : "-"}
                      </td>
                      <td className="py-3 px-4">
                        {doc.viewedAt
                          ? new Date(doc.viewedAt).toLocaleString()
                          : "No visto"}
                      </td>
                      <td className="py-3 px-4">
                        <button
                          onClick={() =>
                            downloadBase64File(doc.base64, doc.name, doc.id)
                          }
                          className="px-3 py-2 text-white bg-[#3a94cc] rounded-lg hover:bg-[#337fb0]"
                        >
                          Descargar
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            ) : (
              <p className="text-center text-gray-600">
                No hay documentos disponibles.
              </p>
            )}
          </div>
        </div>
      )}
    </div>
  );
};
