import React, { useEffect, useState } from "react";
import Cookies from "js-cookie";
import { callApi } from "../../../api/callApi";
import useAuthInterceptor from "../../hooks/useAuthInterceptor";

export const DocumentosWeb = () => {
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
      console.log("Error al marcar como leído:", error);
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
          <p>Cargando...</p>
        </div>
      ) : (
        <div className="p-6 max-w-4xl mx-auto">
          <div className="flex justify-start">
            {Object.keys(data).map((type) => (
              <button
                key={type}
                onClick={() => setSelectedTab(type)}
                className={`px-4 py-2 text-sm font-medium 
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
                      Fecha de Creación
                    </th>
                    <th className="text-left py-3 px-4 font-semibold text-white text-sm">
                      Fecha de Visualización
                    </th>
                    <th className="text-left py-3 px-4 font-semibold text-white text-sm">
                      Acciones
                    </th>
                  </tr>
                </thead>
                <tbody>
                  {data[selectedTab].map((doc) => (
                    <React.Fragment key={doc.id}>
                      <tr className="hover:bg-gray-50">
                        <td className="py-2 px-4">{doc.name}</td>
                        <td className="py-3 px-4">
                          {new Date(doc.createdAt).toLocaleString()}
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
                            className="mt-2 w-full px-3 py-2 text-white bg-[#3a94cc] rounded-lg hover:bg-[#3a94cc]"
                          >
                            Descargar
                          </button>
                        </td>
                      </tr>
                    </React.Fragment>
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
