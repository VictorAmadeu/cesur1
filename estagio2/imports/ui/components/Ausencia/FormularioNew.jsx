import React, { useState } from "react";
import Cookies from "js-cookie";
import { toast } from "react-toastify";
import LicenseService from "/imports/service/licenseService";

export const FormularioNew = ({ onClose, onUpdate }) => {
  const [loadingSubmit, setLoadingSubmit] = useState(false);
  const [showDateTimeInput, setShowDateTimeInput] = useState(true);

  const handleFormSubmit = async (event) => {
    event.preventDefault();
    setLoadingSubmit(true);
    const token = Cookies.get("tokenIntranEK");

    const formData = new FormData(event.target);
    const typeId = parseInt(formData.get("type"));
    const comments = formData.get("comments");
    let dateStart = formData.get("dateStartDate");
    let dateEnd = formData.get("dateEndDate");
    let timeStart = formData.get("dateStartTime");
    let timeEnd = formData.get("dateEndTime");

    try {
      const r = await LicenseService.register({
        type: typeId,
        comments: comments ? comments : "",
        dateStart: dateStart,
        dateEnd: dateEnd,
        timeStart: timeStart,
        timeEnd: timeEnd,
      });
      toast.success(`${r.message}`, { position: "top-center" });
      onUpdate(); // Refresca la lista de ausencias
      onClose(); // Cierra el modal
    } catch (error) {
      toast.error("Error al crear la solicitud", { position: "top-center" });
    } finally {
      setLoadingSubmit(false);
    }
  };

  const closeBtn = () => {
    onClose();
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
        <div className="campo">
          <label htmlFor="type">
            Tipo de ausencia
            <span style={{ color: "red", marginLeft: "4px" }}>*</span>
          </label>
          <select id="type" name="type" required>
            <option value="">Tipo de ausencia</option>
            <option value={1}>Ausencia Personal</option>
            <option value={2}>Baja laboral</option>
            <option value={3}>Vacaciones</option>
          </select>
        </div>
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
              <input type="date" id="dateEndDate" name="dateEndDate" required />
            </div>
            <div className="campo">
              <label htmlFor="dateEndTime">Hora de finalización</label>
              <input type="time" id="dateEndTime" name="dateEndTime" />
            </div>
          </div>
        </div>
        <div className="campo">
          <label htmlFor="comments">Comentario</label>
          <textarea id="comments" name="comments" />
        </div>
        <div className="w-full flex justify-between gap-2">
          <button
            className="w-full bg-[#dc3545] text-white py-2 rounded-md"
            onClick={() => closeBtn()}
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
