// C:\Proyectos\intranek\imports\ui\components\Horario\index.jsx
//
// Etapa 3 — Paso 3.3 (Vista escritorio reutilizando desktop/)
// ----------------------------------------------------------
// Objetivo:
// - Un único punto de carga de datos (WorkSheduleService.getWorkShedule)
// - Un único contrato de datos (useScheduleAdapter)
// - UI por viewport:
//    - Móvil: tarjetas (MovilCard)
//    - Desktop: grilla real (ScheduleGridDesktop reutilizando desktop/)
//
// Producción:
// - Cambios mínimos: no se tocan endpoints ni contratos del backend.
// - Extras: si no existe método disponible, devolvemos { segments: [] } sin romper.

import React, { useEffect, useState } from "react";
import { useMediaQuery } from "react-responsive";

import { usePermissions } from "../../../context/permissionsContext";
import { UnderConstruction } from "../UnderConstruction";
import { DatePickerSelect } from "../DatePickerSelect";

import { useDate } from "../../../provider/date";
import dayjs from "/imports/utils/dayjsConfig";

import WorkSheduleService from "/imports/service/workShedule";

// ✅ Adapter único (Paso 3.1)
import useScheduleAdapter from "../../hooks/useScheduleAdapter";

// ✅ UI móvil
import MovilCard from "./movil/MovilCard";

// ✅ UI escritorio (Paso 3.3)
// IMPORTANTE (producción/Cordova): usa el MISMO casing que la carpeta real.
// Si tu carpeta en disco es "desktop" (minúsculas), el import DEBE ser "./desktop/...".
import ScheduleGridDesktop from "./desktop/ScheduleGridDesktop";

/**
 * getExtraSegmentsSafe
 * -------------------
 * Producción: intentamos cargar extras si existe un método disponible.
 * Métodos posibles vistos entre ramas (sin asumir uno único):
 * - extraByRage (typo histórico)
 * - extraByRange (nombre esperado)
 * - checkExtraSegment (visto en componentes legacy)
 *
 * Si no existe o falla, devolvemos un shape seguro.
 *
 * @param {{ startDate: string, endDate: string }} params
 * @returns {Promise<{segments:any[]}>}
 */
async function getExtraSegmentsSafe(params) {
  try {
    // Evitamos optional chaining por compatibilidad de parser/lint en algunos entornos.
    const candidateFn =
      WorkSheduleService &&
      (WorkSheduleService.extraByRage ||
        WorkSheduleService.extraByRange ||
        WorkSheduleService.checkExtraSegment);

    if (typeof candidateFn === "function") {
      const res = await candidateFn(params);

      // Normalizamos por seguridad (por si backend devuelve array directo)
      if (Array.isArray(res)) return { segments: res };
      if (res && Array.isArray(res.segments)) return res;

      return { segments: [] };
    }

    return { segments: [] };
  } catch (e) {
    // eslint-disable-next-line no-console
    console.warn("[Horario] extras fallback", e);
    return { segments: [] };
  }
}

const Horario = () => {
  const { permissions } = usePermissions();

  // Móvil < 1024px (regla de tu guía)
  const isMobile = useMediaQuery({ query: "(max-width: 1024px)" });

  const { rangeWeek, selectedDate } = useDate();

  // Semana ISO (lunes-domingo) desde el provider.
  // Fallback defensivo por si rangeWeek aún viene null en el primer render.
  const startDate =
    (rangeWeek && rangeWeek.start) ||
    dayjs(selectedDate).startOf("isoWeek").format("YYYY-MM-DD");

  const endDate =
    (rangeWeek && rangeWeek.end) ||
    dayjs(selectedDate).endOf("isoWeek").format("YYYY-MM-DD");

  // Estado crudo (sin adaptar) + extras
  const [loading, setLoading] = useState(true);
  const [rawSchedule, setRawSchedule] = useState(null);
  const [extraSegments, setExtraSegments] = useState({ segments: [] });

  // ✅ Normalizado (contrato único)
  const scheduleByDate = useScheduleAdapter(rawSchedule);

  useEffect(() => {
    let alive = true;

    const load = async () => {
      try {
        setLoading(true);

        // 1) Horario semanal (única llamada)
        const scheduleResp = await WorkSheduleService.getWorkShedule({
          startDate,
          endDate,
        });

        if (alive) setRawSchedule(scheduleResp || {});

        // 2) Extras (seguro)
        const extraResp = await getExtraSegmentsSafe({ startDate, endDate });
        if (alive) setExtraSegments(extraResp || { segments: [] });
      } catch (e) {
        // eslint-disable-next-line no-console
        console.error("[Horario] load error", e);

        if (alive) {
          setRawSchedule({});
          setExtraSegments({ segments: [] });
        }
      } finally {
        if (alive) setLoading(false);
      }
    };

    load();

    return () => {
      alive = false;
    };
  }, [startDate, endDate]);

  // Permisos
  if (!permissions.allowWorkSchedule) {
    return (
      <section>
        <UnderConstruction section="Horario" />
      </section>
    );
  }

  return (
    <section>
      <header className="desplegableFecha mb-4">
        <DatePickerSelect type="week" allowFutureDates={true} />
      </header>

      {loading ? (
        <p className="text-center">Cargando horario...</p>
      ) : isMobile ? (
        // 📱 Móvil: tarjetas (adapter)
        // Nota: pasamos scheduleDay como alias para compatibilidad con componentes legacy / checkJs.
        <MovilCard
          scheduleByDate={scheduleByDate}
          scheduleDay={scheduleByDate}
          weekStartDate={startDate}
        />
      ) : (
        // 🖥️ Escritorio: reutiliza carpeta desktop/ (Paso 3.3)
        <ScheduleGridDesktop
          startDate={startDate}
          endDate={endDate}
          scheduleByDate={scheduleByDate}
          extraSegments={extraSegments}
        />
      )}
    </section>
  );
};

export default Horario;