// C:\Proyectos\intranek\imports\ui\components\Horario\movil\MovilCard.jsx
//
// Etapa 3 ΓÇö Paso 3.2 (Vista m├│vil)
// --------------------------------
// Objetivo:
// - Mantener UX de tarjetas en m├│vil (producci├│n, bajo riesgo).
// - Consumir el contrato ├║nico del adapter (Paso 3.1):
//   { [date]: { segments:[], extraSegments:[], workStart?, workEnd?, status } }
//
// Seguridad:
// - Compatible tambi├⌐n con el ΓÇ£shape legacyΓÇ¥ (hasDay/day/segments/extraSegments)
//   por si alg├║n componente viejo lo sigue usando (no rompe producci├│n).

import React from "react";
import dayjs from "/imports/utils/dayjsConfig";

/**
 * @param {any} v
 * @returns {any[]}
 */
function asArray(v) {
  return Array.isArray(v) ? v : [];
}

/**
 * Etiqueta tolerante para segmentos.
 * @param {any} s
 */
function getSegmentLabel(s) {
  if (!s) return "";
  return s.label || s.name || s.typeLabel || s.type || "";
}

/**
 * Normaliza una entrada diaria a un shape ├║nico para pintar.
 * Soporta:
 *  - Shape normalizado (adapter)
 *  - Shape legacy (m├│vil antiguo)
 *
 * @param {string} dateKey YYYY-MM-DD
 * @param {any} info
 */
function normalizeForCard(dateKey, info) {
  // 1) Shape normalizado (adapter)
  const hasNormalized =
    info &&
    typeof info === "object" &&
    Array.isArray(info.segments) &&
    Array.isArray(info.extraSegments);

  if (hasNormalized) {
    const segments = asArray(info.segments);
    const extraSegments = asArray(info.extraSegments);

    const rawStatus = (info.status || "").toString().toLowerCase();
    const status =
      rawStatus === "laboral" || rawStatus === "libre"
        ? rawStatus
        : segments.length > 0
        ? "laboral"
        : "libre";

    // Horario principal (fallback defensivo)
    const workStart = info.workStart || (segments[0] ? segments[0].start : "") || "";
    const workEnd = info.workEnd || (segments[0] ? segments[0].end : "") || "";

    return { status, segments, extraSegments, workStart, workEnd };
  }

  // 2) Shape legacy (hasDay/day/segments/extraSegments)
  const hasDayFlag = typeof (info && info.hasDay) === "boolean" ? info.hasDay : undefined;

  const daySegments = asArray(info && info.day); // horario principal
  const segmentsField = asArray(info && info.segments); // segmentos
  const extraField = asArray(info && info.extraSegments); // extras

  const primarySegments =
    (hasDayFlag === true || daySegments.length > 0) ? daySegments : segmentsField;

  // Preservamos info: si primary viene de day, tratamos segmentsField como ΓÇ£extrasΓÇ¥ visuales.
  const extraSegments = [];
  if (primarySegments === daySegments && segmentsField.length > 0) {
    extraSegments.push.apply(extraSegments, segmentsField);
  }
  if (extraField.length > 0) {
    extraSegments.push.apply(extraSegments, extraField);
  }

  const d = dayjs(dateKey);
  const isWeekday = d.day() >= 1 && d.day() <= 5;

  const status =
    typeof hasDayFlag === "boolean"
      ? hasDayFlag
        ? "laboral"
        : "libre"
      : primarySegments.length > 0
      ? "laboral"
      : isWeekday
      ? "laboral"
      : "libre";

  const workStart = (primarySegments[0] ? primarySegments[0].start : "") || "";
  const workEnd = (primarySegments[0] ? primarySegments[0].end : "") || "";

  return { status, segments: primarySegments, extraSegments, workStart, workEnd };
}

function isISODate(v) {
  return typeof v === "string" && /^\d{4}-\d{2}-\d{2}$/.test(v);
}

const MovilCard = (props) => {
  // ΓÜá∩╕Å Importante: NO destructuramos en la firma para que TS (checkJs)
  // no ΓÇ£obligueΓÇ¥ a pasar scheduleDay.
  const safeProps = props || {};
  const scheduleByDate = safeProps.scheduleByDate;
  const scheduleDay = safeProps.scheduleDay;
  const weekStartDate = safeProps.weekStartDate;

  // Compatibilidad: aceptamos scheduleByDate (nuevo) o scheduleDay (legacy)
  const data = scheduleByDate || scheduleDay || {};

  // Si tenemos startDate semanal, renderizamos SIEMPRE 7 d├¡as (m├ís estable en m├│vil)
  const dates = isISODate(weekStartDate)
    ? Array.from({ length: 7 }).map((_, i) =>
        dayjs(weekStartDate).add(i, "day").format("YYYY-MM-DD")
      )
    : Object.keys(data).sort();

  if (!dates || dates.length === 0) {
    return <p className="text-center text-gray-500">No hay datos de horario.</p>;
  }

  return (
    <div className="flex flex-col gap-4 p-4 mb-4 max-w-3xl justify-center align-center mx-auto">
      {dates.map((dateKey) => {
        const infoRaw = (data && data[dateKey]) || {};
        const info = normalizeForCard(dateKey, infoRaw);

        const dayName = dayjs(dateKey).format("dddd");
        const dateFormatted = dayjs(dateKey).format("DD/MM/YYYY");

        const isLaboral = info.status === "laboral";
        const badgeClass = isLaboral
          ? "bg-green-100 text-green-800"
          : "bg-red-100 text-red-800";

        return (
          <article
            key={dateKey}
            className="bg-white shadow-md rounded-2xl p-4 border border-gray-200 w-full"
            aria-label={`Horario del ${dayName} ${dateFormatted}`}
          >
            <header className="flex justify-between items-center mb-3">
              <div>
                <h2 className="font-bold text-lg capitalize">{dayName}</h2>
                <p className="text-sm text-gray-500">{dateFormatted}</p>
              </div>

              <span className={`px-3 py-1 text-xs font-semibold rounded-full ${badgeClass}`}>
                {isLaboral ? "Laboral" : "Libre"}
              </span>
            </header>

            {info.workStart && info.workEnd ? (
              <div className="mb-3">
                <p className="text-sm text-gray-700">
                  <strong>Horario:</strong> {info.workStart} - {info.workEnd}
                </p>
              </div>
            ) : null}

            {/* Segmentos principales */}
            <div className="space-y-2">
              <h3 className="text-sm font-semibold text-gray-600">Segmentos:</h3>

              {info.segments.length === 0 ? (
                <p className="text-sm text-gray-500">Sin segmentos.</p>
              ) : (
                info.segments.map((seg, idx) => {
                  const key =
                    (seg && seg.id) || `${dateKey}-${(seg && seg.start) || ""}-${(seg && seg.end) || ""}-${idx}`;
                  const label = getSegmentLabel(seg);

                  return (
                    <div
                      key={key}
                      className="flex justify-between bg-gray-50 p-2 rounded-lg text-sm"
                    >
                      <span>
                        {String((seg && seg.start) || "")} - {String((seg && seg.end) || "")}
                      </span>
                      {label ? <span className="text-gray-600 text-xs">{label}</span> : null}
                    </div>
                  );
                })
              )}
            </div>

            {/* Segmentos extra */}
            {info.extraSegments.length > 0 ? (
              <div className="space-y-2 mt-3">
                <h3 className="text-sm font-semibold text-gray-600">Segmentos extra:</h3>

                {info.extraSegments.map((seg, idx) => {
                  const key =
                    (seg && seg.id) ||
                    `extra-${dateKey}-${(seg && seg.start) || ""}-${(seg && seg.end) || ""}-${idx}`;
                  const label = getSegmentLabel(seg);

                  return (
                    <div
                      key={key}
                      className="flex justify-between bg-gray-50 p-2 rounded-lg text-sm"
                    >
                      <span>
                        {String((seg && seg.start) || "")} - {String((seg && seg.end) || "")}
                      </span>
                      {label ? <span className="text-gray-600 text-xs">{label}</span> : null}
                    </div>
                  );
                })}
              </div>
            ) : null}
          </article>
        );
      })}
    </div>
  );
};

export default MovilCard;
