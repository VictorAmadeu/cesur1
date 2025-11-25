import React, { useState } from "react";
import Cookies from "js-cookie";
import { toast } from "react-toastify";
import LicenseService from "/imports/service/licenseService";
import InputFile from "./InputFile";

/**
 * Formulario para crear una nueva ausencia.
 *
 * - Envía tipo, fechas, horas y comentario.
 * - Permite adjuntar justificantes (máx. 3 ficheros).
 * - Marca los adjuntos como obligatorios para algunos tipos (1 y 2).
 * - Usa el servicio LicenseService.register para llamar a /license/create.
 */
export const FormularioNew = ({ onClose, onUpdate }) => {
  // Estado de carga del botón de enviar.
  const [loadingSubmit, setLoadingSubmit] = useState(false);
  // Lista de ficheros preparados (name, size, content base64).
  const [files, setFiles] = useState([]);
  // Si para el tipo seleccionado el justificante es obligatorio o no.
  const [requiredAttachment, setRequiredAttachment] = useState(false);

  /**
   * Envío del formulario de nueva ausencia.
   */
  const handleFormSubmit = async (event) => {
    event.preventDefault();
    setLoadingSubmit(true);

    // Se mantiene el token por compatibilidad, aunque aquí no se use directamente.
    const token = Cookies.get("tokenIntranEK");

    const formData = new FormData(event.target);

    // ⚠️ formData.get devuelve FormDataEntryValue (string | File | null).
    // Para evitar el error de TypeScript con parseInt, convertimos a string explícitamente.
    const rawType = formData.get("type");
    const typeId = rawType ? parseInt(rawType.toString(), 10) : NaN;

    const comments = formData.get("comments") || "";
    const dateStart = formData.get("dateStartDate");
    const dateEnd = formData.get("dateEndDate");
    const timeStart = formData.get("dateStartTime");
    const timeEnd = formData.get("dateEndTime");

    // Si el tipo requiere justificante y no hay archivos, bloqueamos el envío.
    if (requiredAttachment && files.length === 0) {
      toast.error("Adjunta al menos un justificante para este tipo.", {
        position: "top-center",
      });
      setLoadingSubmit(false);
      return;
    }

    try {
      const r = await LicenseService.register({
        type: typeId,
        comments: comments || "",
        dateStart,
        dateEnd,
        timeStart,
        timeEnd,
        // El backend espera un array de arrays de ficheros.
        files: files.length ? [files] : [],
      });

      toast.success(`${r.message}`, { position: "top-center" });
      onUpdate(); // Refresca la lista de ausencias.
      onClose(); // Cierra el modal.
    } catch (error) {
      toast.error("Error al crear la solicitud", { position: "top-center" });
    } finally {
      setLoadingSubmit(false);
    }
  };

  /**
   * Cierre del modal desde el botón "Cancelar".
   */
  const closeBtn = () => {
    onClose();
  };

  /**
   * Cuando cambia el tipo de ausencia, marcamos si el justificante es obligatorio.
   * Tipos 1 y 2 → requieren adjunto.
   */
  const handleTypeChange = (event) => {
    const value = parseInt(event.target.value, 10);
    setRequiredAttachment([1, 2].includes(value));
  };

  return (
    <div className="bg-white w-full max-w-lg rounded-lg shadow-lg p-4 m-2 relative h-fit">
      <button
        onClick={onClose}
        className="absolute top-2 right-2 text-gray-500"
      >
        X
      </button>

      <h4 className="text-xl font-semibold mb-4">Nueva Ausencia</h4>

      <form onSubmit={handleFormSubmit}>
        {/* Tipo de ausencia */}
        <div className="campo">
          <label htmlFor="type">
            Tipo de ausencia
            <span style={{ color: "red", marginLeft: "4px" }}>*</span>
          </label>
          <select
            id="type"
            name="type"
            required
            onChange={handleTypeChange}
          >
            <option value="">Tipo de ausencia</option>
            <option value={1}>Ausencia Personal (requiere justificante)</option>
            <option value={2}>Baja laboral (requiere justificante)</option>
            <option value={3}>Vacaciones</option>
          </select>
        </div>

        {/* Fechas y horas */}
        <div className="w-full">
          <div>
            <div className="campo">
              <label htmlFor="dateStartDate">
                Fecha de inicio
                <span style={{ color: "red", marginLeft: "4px" }}>*</span>
              </label>
              <input
                type="date"
                id="dateStartDate"
                name="dateStartDate"
                required
              />
            </div>
            <div className="campo">
              <label htmlFor="dateStartTime">Hora de inicio</label>
              <input type="time" id="dateStartTime" name="dateStartTime" />
            </div>
          </div>

          <div>
            <div className="campo">
              <label htmlFor="dateEndDate">
                Fecha de finalización
                <span style={{ color: "red", marginLeft: "4px" }}>*</span>
              </label>
              <input
                type="date"
                id="dateEndDate"
                name="dateEndDate"
                required
              />
            </div>
            <div className="campo">
              <label htmlFor="dateEndTime">Hora de finalización</label>
              <input type="time" id="dateEndTime" name="dateEndTime" />
            </div>
          </div>
        </div>

        {/* Comentario libre */}
        <div className="campo">
          <label htmlFor="comments">Comentario</label>
          <textarea id="comments" name="comments" />
        </div>

        {/* Bloque de adjuntos */}
        <div className="my-4">
          <p className="text-sm text-gray-700 mb-1">
            Adjuntar justificante {requiredAttachment ? "(obligatorio)" : "(opcional)"}.
          </p>
          {/* InputFile se encarga de validar y devolver los ficheros en base64 */}
          <InputFile onFileSelect={setFiles} maxFiles={3} maxSizeMb={5} />
        </div>

        {/* Botones de acción */}
        <div className="w-full flex justify-between gap-2">
          <button
            type="button"
            className="w-full bg-[#dc3545] text-white py-2 rounded-md"
            onClick={closeBtn}
          >
            Cancelar
          </button>
          <button
            type="submit"
            className="w-full bg-[#3a94cc] text-white py-2 rounded-md"
            disabled={loadingSubmit}
          >
            {loadingSubmit ? "Solicitando..." : "Solicitar"}
          </button>
        </div>
      </form>
    </div>
  );
};
