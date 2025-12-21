// C:\Proyectos\intranek\imports\ui\components\Documento\DocumentosMobile.jsx
//
// Componente m├│vil para listar y descargar documentos.
// - Usa file-saver para forzar la descarga en m├│viles.
// - Convierte base64 ΓåÆ Blob con la utilidad base64ToBlob.
// - Marca el documento como le├¡do antes de descargar.
//
// Requisitos:
//   npm i file-saver
//   Tener la utilidad en imports/utils/files.js (esta versi├│n del import asume esa ubicaci├│n).

import React, { useEffect, useState } from "react";
import Cookies from "js-cookie";
import { callApi } from "../../../api/callApi";
import useAuthInterceptor from "../../hooks/useAuthInterceptor";
import { saveAs } from "file-saver";                 // fuerza descarga en Android/iOS
import { base64ToBlob } from "../../../utils/files"; // ΓåÉ OJO: tres niveles (imports/utils/files.js)

export const DocumentoMovil = () => {
  const [loading, setLoading] = useState(true);
  const [data, setData] = useState({});                // { "N├│minas": [...], "Contratos": [...] }
  const [selectedTab, setSelectedTab] = useState(null);

  const callApiWithAuth = useAuthInterceptor(callApi);

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
   * Descarga un documento desde su base64:
   * 1) Marca como le├¡do (no bloquea la descarga si falla).
   * 2) Detecta un MIME razonable.
   * 3) Convierte base64 ΓåÆ Blob y descarga con file-saver.
   */
  const downloadBase64File = async (base64, fileName, id) => {
    try {
      const token = Cookies.get("tokenIntranEK");
      const req = await callApiWithAuth("document/mark-read", { id }, token);
      if (req?.code === "200") {
        getDocs(); // refresca viewedAt
      }
    } catch (error) {
      console.warn("No se pudo marcar como le├¡do:", error);
    }

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

    try {
      const blob = base64ToBlob(base64, mimeType); // tolera data:...;base64,AAA y AAA
      saveAs(blob, fileName || "documento");
    } catch (e) {
      // Fallback por si algo raro pasa con el base64
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
          <span>Cargando...</span>
        </div>
      ) : (
        <div className="p-4">
          {selectedTab && data[selectedTab] && data[selectedTab].length > 0 ? (
            <>
              {/* Selector del tipo de documento */}
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
                    <div className="flex justify-between items-center">
                      <span className="text-lg font-semibold text-gray-800 break-words max-w-full">
                        {doc.name}
                      </span>
                    </div>

                    <div className="mt-2 text-sm text-gray-600">
                      <p>
                        <strong>Creado el:</strong>{" "}
                        {doc.createdAt
                          ? new Date(doc.createdAt).toLocaleString()
                          : "-"}
                      </p>
                      <p>
                        <strong>Visto el:</strong>{" "}
                        {doc.viewedAt
                          ? new Date(doc.viewedAt).toLocaleString()
                          : "No visto"}
                      </p>
                    </div>

                    <button
                      onClick={() =>
                        downloadBase64File(doc.base64, doc.name, doc.id)
                      }
                      className="mt-2 w-full px-3 py-2 text-white bg-[#3a94cc] rounded-lg hover:bg-[#337fb0]"
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
