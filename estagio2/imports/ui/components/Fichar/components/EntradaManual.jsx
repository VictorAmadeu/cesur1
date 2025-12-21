/**
 * EntradaManual.jsx (UNIFICADO desktop + mobile)
 * ------------------------------------------------------------
 * Objetivo:
 * - Unificar validaciones: horas obligatorias, salida > entrada,
 *   mínimo 1 minuto, fecha no futura.
 * - Incluir deviceId en el payload SOLO si allowDeviceRegistration está activo.
 * - Evitar dobles clicks: disabled + loading.
 *
 * Nota de producción:
 * - Estas validaciones mejoran UX, pero NO sustituyen reglas del backend.
 * - No cambiamos contratos de servicios; solo reforzamos el frontend.
 */

import React, { useEffect, useState } from "react";
import { useDate } from "../../../../provider/date";
import { toast } from "react-toastify";
/* @ts-ignore: VSCode/TS Server puede marcar imports CSS en .jsx */
import "react-toastify/dist/ReactToastify.css";

import ProjectSelector from "../ProjectSelector";
import CheckInService from "/imports/service/checkinService.js";
import AuthService from "/imports/service/authService.js";
import dayjs from "dayjs";
import DeviceService from "/imports/service/deviceService";
import { getOrCreateDeviceId } from "/imports/utils/deviceUtils";
import { usePermissions } from "../../../../context/permissionsContext";
import { useNavigate } from "react-router-dom";
import useProjects from "../hooks/useProjects";
import { useCheckin } from "/imports/provider/checkIn";

export const EntradaManual = () => {
  const { selectedDate: date } = useDate();
  const { refreshTimes } = useCheckin();

  const { projects, loadingProjects, selectedProject, setSelectedProject } =
    useProjects();

  const { permissions } = usePermissions();
  const navigate = useNavigate();

  // Inputs hora
  const [startTime, setStartTime] = useState("");
  const [endTime, setEndTime] = useState("");

  // UI flags
  const [loading, setLoading] = useState(false);
  const [disabled, setDisabled] = useState(false);

  /**
   * Validaciones previas (UX):
   * - Horas obligatorias
   * - Salida > entrada
   * - Diferencia mínima de 1 minuto
   * - Fecha no futura
   */
  const validateBeforeSubmit = () => {
    if (!startTime || !endTime) {
      toast.error("Debes indicar hora de entrada y salida.", {
        position: "top-center",
      });
      return false;
    }

    const base = dayjs(date).format("YYYY-MM-DD");
    const start = dayjs(`${base}T${startTime}:00`);
    const end = dayjs(`${base}T${endTime}:00`);

    if (!end.isAfter(start)) {
      toast.error("La hora de salida debe ser posterior a la de entrada.", {
        position: "top-center",
      });
      return false;
    }

    // Refuerzo “mínimo 1 minuto” (por claridad de regla)
    if (end.diff(start, "minute") < 1) {
      toast.error(
        "La diferencia entre entrada y salida debe ser de al menos 1 minuto.",
        { position: "top-center" }
      );
      return false;
    }

    const selectedDay = dayjs(date).startOf("day");
    const today = dayjs().startOf("day");
    if (selectedDay.isAfter(today)) {
      toast.error("No se puede registrar tiempo en una fecha futura.", {
        position: "top-center",
      });
      return false;
    }

    return true;
  };

  /**
   * Interpreta de forma defensiva la verificación de DeviceService.check(...)
   * (sin asumir un contrato exacto).
   */
  const isDeviceVerified = (res) => {
    if (res === true) return true;
    if (!res) return false;

    // Formas típicas: { code: 200 }, { status: 200 }, axios { status: 200 }, { data: { code: 200 } }
    if (res.code === 200 || res.code === "200") return true;
    if (res.status === 200 || res.status === "success") return true;
    if (res.data?.code === 200 || res.data?.code === "200") return true;
    if (res.data?.status === 200 || res.data?.status === "success") return true;

    return false;
  };

  const setTimeToday = async () => {
    try {
      setDisabled(true);
      setLoading(true);

      // 1) Sesión válida
      const checkSession = await AuthService.isAuthenticated();
      if (checkSession.code !== "200") {
        navigate("/login");
        return;
      }

      // 2) Validaciones frontend
      if (!validateBeforeSubmit()) return;

      const registerDeviceEnabled = Boolean(
        permissions?.allowDeviceRegistration
      );
      const allowProjectsEnabled = Boolean(permissions?.allowProjects);

      // 3) DeviceId + verificación (solo si la política lo requiere)
      let deviceId = null;
      if (registerDeviceEnabled) {
        deviceId = await getOrCreateDeviceId();

        const checkRes = await DeviceService.check(deviceId);
        if (!isDeviceVerified(checkRes)) {
          toast.error(
            "Dispositivo no verificado. No se puede registrar el tiempo.",
            { position: "top-center" }
          );
          return;
        }
      }

      // 4) Payload
      const base = dayjs(date).format("YYYY-MM-DD");
      const payload = {
        hourStart: `${base}T${startTime}:00`,
        hourEnd: `${base}T${endTime}:00`,
        ...(allowProjectsEnabled && {
          project: selectedProject?.value ?? null,
        }),
        ...(registerDeviceEnabled && { deviceId }),
      };

      // 5) Registrar en backend
      const res = await CheckInService.registerManual(payload);

      const ok =
        res?.code === 200 ||
        res?.code === "200" ||
        res?.status === 200 ||
        res?.data?.code === 200 ||
        res?.data?.code === "200";

      const msg = res?.message ?? res?.data?.message ?? "Operación realizada.";

      if (ok) {
        toast.success(msg, { position: "top-center" });
        refreshTimes();
        setSelectedProject(false);
      } else {
        toast.error(msg, { position: "top-center" });
      }
    } catch (error) {
      console.error("Error en setTimeToday:", error);
      toast.error("No se pudo registrar el tiempo. Inténtalo de nuevo.", {
        position: "top-center",
      });
    } finally {
      setLoading(false);
      setDisabled(false);
      setStartTime("");
      setEndTime("");
    }
  };

  useEffect(() => {
    setSelectedProject(false);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  return (
    <div className="ficharEntradaManual sectionCuadro w-full p-4 mr-2 flex flex-col gap-4 bg-gray-200 rounded-lg">
      <section className="flex flex-col gap-4 justify-center items-center w-full">
        <div className="flex justify-between items-center text-center w-full">
          <div className="flex flex-col text-center">
            <span>Entrada</span>
            <input
              className="bg-white"
              type="time"
              value={startTime}
              onChange={(e) => setStartTime(e.target.value)}
            />
          </div>

          <div className="flex flex-col text-center">
            <span>Salida</span>
            <input
              className="bg-white"
              type="time"
              value={endTime}
              onChange={(e) => setEndTime(e.target.value)}
            />
          </div>
        </div>
      </section>

      {permissions?.allowProjects && (
        <ProjectSelector
          permissions={permissions}
          projects={projects}
          selectedProject={selectedProject}
          setSelectedProject={setSelectedProject}
          loading={loadingProjects}
          timesForDay={null}
        />
      )}

      <button
        disabled={disabled || loading}
        className={`text-white py-2 px-5 border rounded-[10px] text-base shadow-md transition-all ease-linear ${
          disabled || loading
            ? "bg-[#e28149a9] border-[#e28149a9] cursor-not-allowed"
            : "bg-[#e28049] border-[#e28049] cursor-pointer"
        }`}
        onClick={setTimeToday}
      >
        Añadir registro
      </button>
    </div>
  );
};
