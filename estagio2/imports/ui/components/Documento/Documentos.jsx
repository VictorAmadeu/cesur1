// imports/ui/components/Documento/Documentos.jsx
//
// Componente único responsivo para Documentos.
// Sustituye a DocumentosWeb.jsx y DocumentosMobile.jsx sin romper producción.
//
// - Desktop (>= 1024px): tabla con columnas Nombre, Creación, Leído, Acciones.
// - Mobile  (< 1024px): select + tarjetas con botón Descargar.
//
// Nota: la lógica (API, base64, Cordova, mark-read) vive en el hook useDocuments.

import React, { useEffect } from 'react';
import { useMediaQuery } from 'react-responsive';
import useDocuments from '../../hooks/useDocuments';

export default function Documentos() {
  const isMobile = useMediaQuery({ query: '(max-width: 1024px)' });

  const {
    loading,
    error,
    dataByType,
    selectedType,
    types,
    load,
    setType,
    download,
  } = useDocuments();

  // Cargar documentos al montar
  useEffect(() => {
    load();
  }, [load]);

  const currentDocs =
    selectedType && dataByType && dataByType[selectedType]
      ? dataByType[selectedType]
      : [];

  if (loading) {
    return (
      <div className="w-full flex justify-center">
        <span>Cargando…</span>
      </div>
    );
  }

  if (error) {
    return <p className="text-center text-gray-600">{error}</p>;
  }

  if (!types || types.length === 0) {
    return <p className="text-center text-gray-600">No hay documentos disponibles.</p>;
  }

  return (
    <div className={isMobile ? 'p-4' : 'p-6 max-w-4xl mx-auto'}>
      {/* Selector de tipo de documento */}
      <div className="mb-4">
        <select
          className="w-full px-4 py-2 rounded-lg border border-gray-300"
          value={selectedType || ''}
          onChange={(e) => setType(e.target.value)}
        >
          {types.map((type) => (
            <option key={type} value={type}>
              {type}
            </option>
          ))}
        </select>
      </div>

      {!currentDocs || currentDocs.length === 0 ? (
        <p className="text-center text-gray-600">No hay documentos disponibles.</p>
      ) : isMobile ? (
        <div className="space-y-4">
          {currentDocs.map((doc) => (
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
                  <strong>Creado el:</strong>{' '}
                  {doc.createdAt ? new Date(doc.createdAt).toLocaleString() : 'N/D'}
                </p>
                <p>
                  <strong>Visto el:</strong>{' '}
                  {doc.viewedAt ? new Date(doc.viewedAt).toLocaleString() : 'No visto'}
                </p>
              </div>

              <button
                onClick={() => download(doc)}
                className="mt-2 w-full px-3 py-2 text-white bg-[#3a94cc] rounded-lg hover:bg-[#337fb0]"
              >
                Descargar
              </button>
            </div>
          ))}
        </div>
      ) : (
        <div className="w-full overflow-x-auto">
          <table className="min-w-full bg-white border border-gray-200 rounded-lg">
            <thead>
              <tr className="bg-[#3a94cc]">
                <th className="text-left py-3 px-4 font-semibold text-white text-sm">
                  Nombre
                </th>
                <th className="text-left py-3 px-4 font-semibold text-white text-sm">
                  Creación
                </th>
                <th className="text-left py-3 px-4 font-semibold text-white text-sm">
                  Leído
                </th>
                <th className="text-left py-3 px-4 font-semibold text-white text-sm">
                  Acciones
                </th>
              </tr>
            </thead>

            <tbody>
              {currentDocs.map((doc) => (
                <tr className="hover:bg-gray-50" key={doc.id}>
                  <td className="py-2 px-4">{doc.name}</td>
                  <td className="py-3 px-4">
                    {doc.createdAt ? new Date(doc.createdAt).toLocaleString() : '-'}
                  </td>
                  <td className="py-3 px-4">
                    {doc.viewedAt ? new Date(doc.viewedAt).toLocaleString() : 'No visto'}
                  </td>
                  <td className="py-3 px-4">
                    <button
                      onClick={() => download(doc)}
                      className="px-3 py-2 text-white bg-[#3a94cc] rounded-lg hover:bg-[#337fb0]"
                    >
                      Descargar
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
