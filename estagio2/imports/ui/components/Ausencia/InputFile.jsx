// @ts-nocheck
import React, { useState } from "react";
import { toast } from "react-toastify";

/**
 * Componente de input de archivos para adjuntar justificantes.
 *
 * - Limita el número máximo de archivos (maxFiles).
 * - Valida extensión y tamaño máximo (maxSizeMb, por defecto 5 MB).
 * - Convierte los ficheros seleccionados a base64 y los devuelve al padre
 *   mediante la función onFileSelect.
 */
const InputFile = ({ onFileSelect, maxFiles, maxSizeMb = 5 }) => {
  // Estado local con la lista de archivos seleccionados (ya convertidos a base64).
  const [files, setFiles] = useState([]);

  /**
   * Maneja la selección de nuevos archivos desde el input.
   */
  const handleFileChange = (event) => {
    const selectedFiles = Array.from(event.target.files);

    // Comprobamos el límite de cantidad de archivos.
    if (selectedFiles.length + files.length > maxFiles) {
      toast.error(`Solo puedes seleccionar hasta ${maxFiles} archivo(s).`, {
        position: "top-center",
      });
      return;
    }

    // Extensiones permitidas y tamaño máximo en bytes.
    const allowed = ["pdf", "jpg", "jpeg", "png"];
    const maxBytes = maxSizeMb * 1024 * 1024;

    // Validamos cada archivo y lo convertimos a base64.
    const filePromises = selectedFiles
      .map((file) => {
        const ext = file.name.split(".").pop().toLowerCase();

        // Validación de extensión
        if (!allowed.includes(ext)) {
          toast.error("Extensión no permitida. Usa PDF/JPG/PNG.", {
            position: "top-center",
          });
          return null;
        }

        // Validación de tamaño
        if (file.size > maxBytes) {
          toast.error(`Archivo supera ${maxSizeMb} MB: ${file.name}`, {
            position: "top-center",
          });
          return null;
        }

        // Conversión a base64
        return new Promise((resolve) => {
          const reader = new FileReader();
          reader.onloadend = () => {
            resolve({
              name: file.name,
              size: file.size,
              content: reader.result.split(",")[1], // base64 sin el prefijo "data:..."
            });
          };
          reader.readAsDataURL(file);
        });
      })
      // Eliminamos los null que provienen de archivos no válidos.
      .filter(Boolean);

    // Cuando se han convertido todos, actualizamos estado local y notificamos al padre.
    Promise.all(filePromises).then((encodedFiles) => {
      const updated = [...files, ...encodedFiles];
      setFiles(updated);
      onFileSelect(updated);
    });
  };

  /**
   * Elimina un archivo de la lista local y notifica al componente padre.
   */
  const handleFileRemove = (index) => {
    const updated = files.filter((_, i) => i !== index);
    setFiles(updated);
    onFileSelect(updated);
  };

  return (
    <div className="w-full">
      {/* Zona clickable para abrir el selector de archivos */}
      <label
        htmlFor="fileInput"
        className="flex flex-col justify-center w-full h-24 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100"
      >
        <div className="flex flex-col items-center justify-center py-2 w-full">
          <p className="mb-1 text-sm text-gray-600">
            <span className="font-semibold">Haz click para cargar archivos</span>
          </p>
          <p className="text-xs text-gray-500">
            PDF/JPG/PNG • Máx {maxSizeMb} MB • Hasta {maxFiles} archivo(s)
          </p>
        </div>
      </label>

      {/* Input real de tipo file (oculto) */}
      <input
        type="file"
        id="fileInput"
        name="fileInput"
        onChange={handleFileChange}
        className="hidden"
        accept=".pdf,.jpg,.jpeg,.png"
        multiple
      />

      {/* Listado de archivos seleccionados */}
      {files.length > 0 && (
        <div className="mt-2 space-y-1">
          {files.map((file, index) => (
            <div
              key={index}
              className="flex items-center justify-between text-sm text-gray-700"
            >
              <span>
                {file.name} ({(file.size / 1024).toFixed(0)} KB)
              </span>
              <button
                type="button"
                onClick={() => handleFileRemove(index)}
                className="text-xs bg-[#dc3545] text-white rounded px-2 py-1"
              >
                Eliminar
              </button>
            </div>
          ))}
        </div>
      )}
    </div>
  );
};

export default InputFile;
