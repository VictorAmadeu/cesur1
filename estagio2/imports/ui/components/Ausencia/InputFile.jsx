// @ts-nocheck
import React, { useState } from 'react';
import { toast } from 'react-toastify';

const InputFile = ({ onFileSelect, maxFiles }) => {
  const [files, setFiles] = useState([]);

  const handleFileChange = (event) => {
    const selectedFiles = Array.from(event.target.files);
    if (selectedFiles.length + files.length > maxFiles) {
      toast.error(`Solo puedes seleccionar hasta ${maxFiles} archivo(s).`, {
        position: 'top-center'
      });
      return; // Si se seleccionan más de los permitidos, no se actualiza el estado.
    }

    // Convertir archivos a base64
    const filePromises = selectedFiles.map((file) => {
      return new Promise((resolve) => {
        const reader = new FileReader();
        reader.onloadend = () => {
          resolve({
            name: file.name,
            content: reader.result.split(',')[1] // Extraer solo el contenido base64
          });
        };
        reader.readAsDataURL(file); // Leer el archivo como base64
      });
    });

    // Después de convertir los archivos, actualiza el estado y pasa los archivos al padre
    Promise.all(filePromises).then((encodedFiles) => {
      setFiles((prevFiles) => [...prevFiles, ...encodedFiles]); // Agregar nuevos archivos al estado
      onFileSelect([...files, ...encodedFiles]); // Pasamos los archivos convertidos a base64
    });
  };

  const handleFileRemove = (index) => {
    // Eliminar el archivo localmente
    setFiles((prevFiles) => {
      const updatedFiles = prevFiles.filter((_, i) => i !== index);
      onFileSelect(updatedFiles); // Actualizamos el estado en el padre con los archivos restantes
      return updatedFiles;
    });
  };

  return (
    <div className="w-full">
      <div className="w-full mx-auto">
        <label
          htmlFor="fileInput"
          className="flex flex-col justify-center w-full h-24 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100 px-auto"
        >
          <div className="flex flex-col items-center justify-center py-2 w-full">
            <p className="mb-2 text-sm text-gray-500">
              <span className="font-semibold">Haz click para cargar archivos</span>
            </p>
            <p className="text-xs text-gray-500">PDF, SVG, PNG, JPG, JPEG o GIF</p>
          </div>
        </label>
        <input
          type="file"
          id="fileInput"
          name="fileInput"
          onChange={handleFileChange}
          className="hidden"
          accept=".svg,.png,.jpg,.jpeg,.gif,.pdf"
          multiple // Permite seleccionar varios archivos
        />
        {files.length > 0 && (
          <div className="mb-2">
            <div className="space-y-1">
              {files.map((file, index) => (
                <div key={index} className="flex items-center justify-between gap-2">
                  <span className="text-sm text-gray-500">{file.name}</span>
                  <button
                    type="button"
                    onClick={() => handleFileRemove(index)}
                    className="text-xs bg-[#dc3545] text-white rounded p-2"
                  >
                    Eliminar
                  </button>
                </div>
              ))}
            </div>
          </div>
        )}
      </div>
    </div>
  );
};

export default InputFile;
