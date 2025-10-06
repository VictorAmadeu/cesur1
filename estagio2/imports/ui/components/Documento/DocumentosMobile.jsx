import React, { useEffect, useState } from "react";
import Cookies from "js-cookie";
import { callApi } from "../../../api/callApi";
import useAuthInterceptor from "../../hooks/useAuthInterceptor";

export const DocumentoMovil = (baseUrl) => {
  const [loading, setLoading] = useState(true);
  const [data, setData] = useState({});
  const [selectedTab, setSelectedTab] = useState(null);

  const callApiWithAuth = useAuthInterceptor(callApi);

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

  useEffect(() => {
    getDocs();
  }, []);

  const downloadBase64File = async (base64, fileName, id) => {
    try {
      const token = Cookies.get("tokenIntranEK");
      const req = await callApiWithAuth(
        "document/mark-read",
        { id: id },
        token
      );
      if (req.code === "200") {
        getDocs();
      }
    } catch (error) {
      alert("Error al marcar el documento como leído.");
    }
    const link = document.createElement("a");
    link.href = `data:application/octet-stream;base64,${base64}`;
    link.download = fileName;
    document.body.appendChild(link);
    link.click();
    link.remove();
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
              {/* Select para seleccionar el tipo de documento */}
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
                        {new Date(doc.createdAt).toLocaleString()}
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
