// imports/ui/components/Horario/ScheduleGrid.jsx
import React from "react";
import dayjs from "/imports/utils/dayjsConfig";

// Genera los 7 días de la semana a partir de startDate
function buildWeek(startDate) {
  return Array.from({ length: 7 }).map((_, i) => {
    const d = dayjs(startDate).add(i, "day");
    return {
      date: d.format("YYYY-MM-DD"),
      label: d.format("dddd"),
      prettyDate: d.format("DD/MM"),
      d,
    };
  });
}

// Extras que afectan a un día concreto (empieza, termina o atraviesa el día)
function extrasForDay(extras = [], day) {
  return extras.filter((seg) => {
    const start = dayjs(seg?.dateStart);
    const end = dayjs(seg?.dateEnd);
    return (
      (start.isValid() && start.isSame(day, "day")) ||
      (end.isValid() && end.isSame(day, "day")) ||
      (start.isValid() && end.isValid() && start.isBefore(day, "day") && end.isAfter(day, "day"))
    );
  });
}

// Intenta obtener una hora HH:mm desde varias posibles keys
const pickTime = (obj, candidates = []) =>
  candidates.map((k) => obj?.[k]).find((v) => typeof v === "string" && v);

// Deriva “estado del día” y “horario principal” con fallbacks suaves.
// Regla solicitada: si no hay datos y el día es L–V => laboral.
function deriveDayInfo(dayData = {}, segments = [], isWeekday = false) {
  const start =
    pickTime(dayData, ["workStart", "start", "startTime", "scheduleStart"]) || null;
  const end =
    pickTime(dayData, ["workEnd", "end", "endTime", "scheduleEnd"]) || null;

  // Si el backend trae un campo status/type lo usamos tal cual
  const rawStatus = (dayData?.status || dayData?.type || "").toString().toLowerCase();

  let status = rawStatus;
  if (!status) {
    if (start && end) status = "laboral";
    else if (segments.length > 0) status = "laboral";
    else status = isWeekday ? "laboral" : "libre"; // ✅ regla L–V por defecto
  }

  // Normalizamos
  if (["work", "working", "laboral", "labor"].includes(status)) status = "laboral";
  if (["free", "off", "libre", "holiday"].includes(status)) status = "libre";

  return { status, workStart: start, workEnd: end };
}

// Etiqueta de segmento tolerante a distintas keys
const getSegmentLabel = (s) => s?.label ?? s?.name ?? s?.typeLabel ?? s?.type ?? "";

const ScheduleGrid = ({
  startDate,
  endDate, // (conservado por contrato)
  scheduleByDate = {},
  extraSegments = { segments: [] },
}) => {
  const days = buildWeek(startDate);
  const extras = Array.isArray(extraSegments?.segments) ? extraSegments.segments : [];

  return (
    // Layout “cards” unificado (móvil y desktop)
    <div className="mx-auto w-full max-w-[960px] px-3 space-y-6">
      {days.map(({ date, label, prettyDate, d }) => {
        const dayData = scheduleByDate?.[date] || {};
        const segments = Array.isArray(dayData?.segments) ? dayData.segments : [];
        const dayExtras = extrasForDay(extras, d);

        // dayjs().day(): 0=Dom, 6=Sáb → weekday = L–V (1..5)
        const isWeekday = d.day() >= 1 && d.day() <= 5;
        const { status, workStart, workEnd } = deriveDayInfo(dayData, segments, isWeekday);

        const isLaboral = status === "laboral";
        const badgeClass = isLaboral
          ? "bg-emerald-100 text-emerald-700"
          : "bg-rose-100 text-rose-700";

        return (
          <article
            key={date}
            className="rounded-2xl shadow border bg-white p-5 md:p-6"
            aria-label={`Horario del ${label} ${prettyDate}`}
          >
            {/* Cabecera: Día + fecha + píldora de estado */}
            <header className="flex items-start justify-between">
              <div>
                <h3 className="text-xl font-semibold capitalize">{label}</h3>
                <p className="text-sm text-gray-500">{prettyDate}</p>
              </div>

              <span
                className={`h-7 inline-flex items-center rounded-full px-3 text-sm font-medium ${badgeClass}`}
                title={isLaboral ? "Día laboral" : "Día libre"}
              >
                {isLaboral ? "Laboral" : "Libre"}
              </span>
            </header>

            {/* “Horario: 09:00 - 18:00” si tenemos ambos extremos */}
            {(workStart && workEnd) && (
              <p className="mt-4 text-sm">
                <span className="font-semibold">Horario:</span>{" "}
                {workStart} - {workEnd}
              </p>
            )}

            {/* Segmentos con chip de horas + etiqueta alineada a la derecha */}
            <div className="mt-3">
              <p className="text-sm font-medium mb-2">Segmentos:</p>

              {segments.length === 0 ? (
                <p className="text-sm text-gray-500">Sin segmentos.</p>
              ) : (
                <div className="space-y-2">
                  {segments.map((s, i) => {
                    const id = s?.id ?? `${date}-${s?.start ?? ""}-${s?.end ?? ""}-${i}`;
                    const labelRight = getSegmentLabel(s);

                    return (
                      <div
                        key={id}
                        className="flex items-center justify-between rounded-lg border bg-gray-50 px-3 py-2"
                      >
                        <span className="inline-flex items-center rounded-full bg-white border px-2 py-1 text-xs font-medium">
                          {(s?.start ?? "").toString()} - {(s?.end ?? "").toString()}
                        </span>

                        {labelRight ? (
                          <span className="ml-3 text-sm text-gray-500">{labelRight}</span>
                        ) : null}
                      </div>
                    );
                  })}
                </div>
              )}
            </div>

            {/* Contador de extras del día */}
            {dayExtras.length > 0 && (
              <div className="mt-3">
                <span className="text-xs rounded-full bg-blue-50 text-blue-600 px-2 py-0.5">
                  {dayExtras.length} extra{dayExtras.length > 1 ? "s" : ""}
                </span>
              </div>
            )}
          </article>
        );
      })}
    </div>
  );
};

export default ScheduleGrid;
