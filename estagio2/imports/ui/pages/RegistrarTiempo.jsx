/**
 * Página: RegistrarTiempoPage
 *
 * Objetivo del ajuste:
 * - Evitar fichajes con fecha FUTURA desde la UI sin romper la navegación.
 * - Respetar la selección del usuario cuando es HOY o una fecha PASADA
 *   (por ejemplo, si viene de "Mis tiempos" y quiere consultar/registrar ese día).
 *
 * Cómo funciona:
 * - Al montar, comprobamos sesión (comportamiento original).
 * - Observamos la fecha seleccionada en el DateProvider:
 *     - Si es inválida o FUTURA ⇒ la normalizamos a HOY (00:00).
 *     - Si es HOY o PASADA ⇒ NO la tocamos.
 *
 * Notas de producción:
 * - Esta defensa de UI complementa la validación de negocio del backend.
 * - No cambia contratos ni rutas; solo evita estados incoherentes en frontend.
 */

import React, { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";

// Servicios/Componentes existentes
import AuthService from "/imports/service/authService";
import { Loading } from "../components/Loading";
import IndexRegister from "../components/Fichar/IndexRegister";

// Contexto de fecha global de la app
import { useDate } from "/imports/provider/date";

// dayjs centralizado (locale/TZ configuradas en el proyecto)
import dayjs from "/imports/utils/dayjsConfig";

export const RegistrarTiempoPage = () => {
  const navigate = useNavigate();
  const [isLoading, setIsLoading] = useState(true);

  // Fecha global seleccionada y setter expuestos por el DateProvider
  const { selectedDate, setDate } = useDate();

  useEffect(() => {
    // 1) Mantiene la comprobación de sesión original
    keepAliveCheck();

    // 2) Defensa anti-fecha-futura en UI:
    //    - Normalizamos "hoy" y "seleccionada" a inicio de día para comparar por día.
    //    - Si la seleccionada es inválida o futura, fijamos HOY.
    const today = dayjs().startOf("day");
    const selected = selectedDate ? dayjs(selectedDate).startOf("day") : null;

    const selectedIsInvalid = !selected || !selected.isValid();
    const selectedIsFuture = selected ? selected.isAfter(today) : false;

    if (selectedIsInvalid || selectedIsFuture) {
      // Evitamos setear si ya está en hoy, para prevenir renders innecesarios.
      const value = today.toDate();
      if (!selected || !selected.isSame(today)) {
        setDate(value);
      }
    }
    // Queremos reaccionar solo a cambios reales de selectedDate (flujo de usuario).
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [selectedDate]);

  // Verifica si el usuario sigue autenticado
  const keepAliveCheck = async () => {
    try {
      const r = await AuthService.isAuthenticated();

      if (r.code === "200" || r.code === 200) {
        // Caso "primer acceso" con cambio de password obligatorio
        if (r.key === "FIRST_TIME") {
          navigate("/change-password");
        }
        setIsLoading(false);
      } else {
        // No autenticado → redirige a login
        navigate("/login");
        setIsLoading(false);
      }
    } catch (_err) {
      // Por seguridad, tratamos cualquier error como no autenticado
      navigate("/login");
      setIsLoading(false);
    }
  };

  if (isLoading) {
    return <Loading text="Comprobando sesión" />;
  }

  return (
    <div>
      <h2 className="text-2xl font-semibold text-center text-gray-700 mt-4">
        Fichar
      </h2>

      {/* IndexRegister consume la fecha del DateProvider.
          - Si el usuario venía de una fecha pasada, se respeta.
          - Si venía con una fecha futura, se normalizó a HOY. */}
      <IndexRegister />
    </div>
  );
};
