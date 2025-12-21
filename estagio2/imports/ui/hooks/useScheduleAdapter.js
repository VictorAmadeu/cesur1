// imports/ui/hooks/useScheduleAdapter.js
//
// Adaptador único de datos para Horario (Paso 3.1)
// ------------------------------------------------
// Objetivo (Etapa 3):
// - Recibir la respuesta "tal cual" de WorkSheduleService.getWorkShedule(...)
// - Normalizarla a una estructura uniforme:
//
// {
//   [date]: {
//     segments: [...],        // franjas laborales principales
//     extraSegments: [...],   // horas extra u otras franjas
//     workStart: 'HH:mm',     // opcional
//     workEnd: 'HH:mm',       // opcional
//     status: 'laboral' | 'libre' | ...
//   }
// }
//
// Importante (producción):
// - Este archivo NO toca servicios, NO hace llamadas HTTP y NO cambia lógica de negocio.
// - Solo transforma datos en memoria para que Desktop/Móvil consuman el mismo contrato.
// - Incluye guardas defensivas para no romper la UI ante respuestas incompletas.
//
// Referencia: Etapa 3 – Paso 3.1 (adaptador único de datos).
// :contentReference[oaicite:2]{index=2}

import { useMemo } from "react";

/**
 * @typedef {Object} ScheduleSegment
 * @property {string|number} [id]
 * @property {string} [start]      // "HH:mm"
 * @property {string} [end]        // "HH:mm"
 * @property {string} [type]       // opcional (ej: incidencias)
 * @property {string} [dateStart]  // opcional (ISO o YYYY-MM-DD)
 * @property {string} [dateEnd]    // opcional (ISO o YYYY-MM-DD)
 * @property {string} [name]       // opcional
 */

/**
 * @typedef {Object} NormalizedScheduleDay
 * @property {ScheduleSegment[]} segments
 * @property {ScheduleSegment[]} extraSegments
 * @property {string} [workStart]
 * @property {string} [workEnd]
 * @property {"laboral"|"libre"|string} status
 */

/**
 * Guarda simple: ¿es un objeto plano?
 * @param {any} v
 */
function isPlainObject(v) {
  return v !== null && typeof v === "object" && !Array.isArray(v);
}

/**
 * Normaliza "cualquier cosa" a array (si no lo es, devuelve []).
 * @template T
 * @param {any} v
 * @returns {T[]}
 */
function asArray(v) {
  return Array.isArray(v) ? v : [];
}

/**
 * Detecta si una key parece una fecha "YYYY-MM-DD".
 * (No forzamos dayjs aquí para mantener el adaptador liviano.)
 * @param {string} key
 */
function looksLikeDateKey(key) {
  return /^\d{4}-\d{2}-\d{2}$/.test(key);
}

/**
 * Extrae workStart/workEnd desde el primer segmento válido que tenga start/end.
 * @param {ScheduleSegment[]} segments
 */
function inferWorkRangeFromSegments(segments) {
  const first = Array.isArray(segments) ? segments.find((s) => s?.start && s?.end) : null;
  if (!first) return { workStart: undefined, workEnd: undefined };
  return { workStart: first.start, workEnd: first.end };
}

/**
 * Normaliza una entrada diaria que puede venir en:
 * - Shape móvil legacy (hasDay/hasSegments/day/segments/extraSegments)
 * - Shape ya unificado (segments/extraSegments/status/workStart/workEnd)
 *
 * @param {any} entry
 * @returns {NormalizedScheduleDay}
 */
function normalizeDayEntry(entry) {
  // Caso 1: ya viene con el shape objetivo (o muy cercano)
  if (isPlainObject(entry) && Array.isArray(entry.segments) && Array.isArray(entry.extraSegments)) {
    const { workStart, workEnd } = inferWorkRangeFromSegments(entry.segments);

    return {
      segments: entry.segments,
      extraSegments: entry.extraSegments,
      workStart: entry.workStart ?? workStart,
      workEnd: entry.workEnd ?? workEnd,
      status: entry.status ?? (entry.segments.length > 0 ? "laboral" : "libre"),
    };
  }

  // Caso 2: shape móvil legacy (según MovilCard.jsx: hasDay, day, segments, extraSegments, etc.)
  const hasDayFlag = typeof entry?.hasDay === "boolean" ? entry.hasDay : undefined;

  // "day" en móvil suele ser el bloque principal (horario laboral).
  const daySegments = asArray(entry?.day);

  // "segments" en móvil son segmentos adicionales (no siempre extras) -> los preservamos sin perderlos.
  const segmentsField = asArray(entry?.segments);

  // "extraSegments" en móvil suele ser la lista de extras.
  const extraField = asArray(entry?.extraSegments);

  // Heurística segura:
  // - Si hay daySegments (o hasDay=true), eso es el "segments" principal.
  // - Si no, y existe segmentsField, lo tomamos como principal (para no dejarlo vacío).
  const primarySegments =
    (hasDayFlag === true || daySegments.length > 0) ? daySegments : segmentsField;

  // extraSegments:
  // - Si primarySegments vino de daySegments, añadimos segmentsField como extras (para NO perder info).
  // - Luego añadimos extraField (extras explícitos).
  const extras = [];
  if (primarySegments === daySegments && segmentsField.length > 0) {
    extras.push(...segmentsField);
  }
  if (extraField.length > 0) {
    extras.push(...extraField);
  }

  const { workStart, workEnd } = inferWorkRangeFromSegments(primarySegments);

  // status:
  // - Si existe hasDay, es la fuente más fiable para "Laboral/Libre" (sin inventar).
  // - Si no, inferimos por presencia de segmentos.
  const status =
    typeof hasDayFlag === "boolean"
      ? (hasDayFlag ? "laboral" : "libre")
      : (primarySegments.length > 0 ? "laboral" : "libre");

  return {
    segments: primarySegments,
    extraSegments: extras,
    workStart,
    workEnd,
    status,
  };
}

/**
 * Adaptador principal (pure function).
 * Recibe la respuesta cruda de WorkSheduleService.getWorkShedule(...) y devuelve:
 * { [date]: NormalizedScheduleDay }
 *
 * @param {any} raw
 * @returns {Record<string, NormalizedScheduleDay>}
 */
export function adaptWorkScheduleByDate(raw) {
  // 1) Si viene vacío o inválido, devolvemos mapa vacío (seguro para UI).
  if (!raw) return {};

  // 2) Caso típico: objeto con keys fecha -> info
  if (isPlainObject(raw)) {
    const keys = Object.keys(raw);

    // Si parece realmente un mapa por fecha, lo normalizamos por entry.
    const isDateMap = keys.length === 0 || keys.every((k) => looksLikeDateKey(k));

    if (isDateMap) {
      /** @type {Record<string, NormalizedScheduleDay>} */
      const out = {};
      keys.forEach((dateKey) => {
        out[dateKey] = normalizeDayEntry(raw[dateKey]);
      });
      return out;
    }

    // Si no parece un mapa por fecha, igual intentamos ser defensivos:
    // - si hay una propiedad "data" que sea mapa/array, la usamos.
    if (raw.data) return adaptWorkScheduleByDate(raw.data);

    // Último fallback: no sabemos el shape -> devolvemos vacío para no romper.
    return {};
  }

  // 3) Caso alternativo: array (poco probable en tu UI actual, pero lo soportamos sin romper).
  // Intentamos agrupar por un campo de fecha si existiera.
  if (Array.isArray(raw)) {
    /** @type {Record<string, NormalizedScheduleDay>} */
    const out = {};

    raw.forEach((item) => {
      // Intentamos encontrar una fecha razonable en orden de preferencia
      const dateKey =
        (typeof item?.date === "string" && item.date.slice(0, 10)) ||
        (typeof item?.day === "string" && item.day.slice(0, 10)) ||
        (typeof item?.dateStart === "string" && item.dateStart.slice(0, 10)) ||
        null;

      if (!dateKey || !looksLikeDateKey(dateKey)) return;

      // Si hay múltiples items del mismo día, los acumulamos.
      const prev = out[dateKey] || {
        segments: [],
        extraSegments: [],
        status: "libre",
      };

      const normalized = normalizeDayEntry(item);

      out[dateKey] = {
        segments: [...prev.segments, ...normalized.segments],
        extraSegments: [...prev.extraSegments, ...normalized.extraSegments],
        workStart: prev.workStart ?? normalized.workStart,
        workEnd: prev.workEnd ?? normalized.workEnd,
        status:
          prev.status === "laboral" || normalized.status === "laboral" ? "laboral" : "libre",
      };
    });

    return out;
  }

  // 4) Si llega un tipo inesperado, devolvemos vacío.
  return {};
}

/**
 * Hook (opcional) para memoizar la adaptación y evitar recomputar en cada render.
 * Úsalo cuando tengas "rawSchedule" en state y quieras obtener "scheduleByDate" normalizado.
 *
 * @param {any} rawSchedule
 */
export function useScheduleAdapter(rawSchedule) {
  return useMemo(() => adaptWorkScheduleByDate(rawSchedule), [rawSchedule]);
}

export default useScheduleAdapter;
