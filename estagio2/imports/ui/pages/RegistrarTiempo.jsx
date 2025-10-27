/**
 * Página: RegistrarTiempoPage
 * Objetivo del cambio:
 *  - Forzar que la fecha seleccionada en el flujo de fichaje sea SIEMPRE "hoy".
 *  - Evitar efectos colaterales donde una fecha futura previamente elegida en la UI
 *    (o en otro módulo) quede latente en el DateProvider y permita intentar fichar
 *    sobre una fecha > hoy.
 *
 * Cómo lo hace:
 *  - Usa el DateProvider (useDate) para establecer, al montar la página, la fecha
 *    global a dayjs().startOf('day').toDate() (hoy a las 00:00 en la TZ configurada).
 *  - Esto asegura que los componentes hijos (IndexRegister y derivados) trabajen
 *    con "hoy" y no con una fecha futura que pudiera estar en el estado global.
 *
 * Nota:
 *  - Este refuerzo es a nivel de UI/estado. La validación de negocio en backend
 *    debe existir y seguirá siendo la autoridad para rechazar fichajes de fechas
 *    futuras. Este cambio no rompe compatibilidad con producción.
 */

import React, { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import AuthService from "/imports/service/authService";
import { Loading } from "../components/Loading";
import IndexRegister from "../components/Fichar/IndexRegister";

// [NUEVO] Provider de fecha usado de forma global en la app.
import { useDate } from "/imports/provider/date";

// [NUEVO] dayjs centralizado (con locale/TZ ya configuradas en el proyecto).
import dayjs from "/imports/utils/dayjsConfig";

export const RegistrarTiempoPage = () => {
  const navigate = useNavigate();
  const [isLoading, setIsLoading] = useState(true);

  // [NUEVO] Obtenemos setDate del DateProvider para fijar la fecha global.
  const { setDate } = useDate();

  useEffect(() => {
    // 1) Comprobación de sesión (mantiene el comportamiento original).
    keepAliveCheck();

    // 2) Refuerzo anti-fecha-futura en la UI:
    //    Al montar, fijamos la fecha seleccionada global a "hoy" (00:00),
    //    evitando que quede cualquier fecha futura arrastrada desde otros flujos.
    //    Usamos startOf('day') para normalizar y no arrastrar horas/minutos.
    const today = dayjs().startOf("day").toDate();
    setDate(today);

    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []); // Dependencias vacías: solo al montar

  // Mantiene la lógica original pero con try/catch para robustez.
  const keepAliveCheck = async () => {
    try {
      const r = await AuthService.isAuthenticated();

      if (r.code === "200") {
        if (r.key === "FIRST_TIME") {
          // Usuario con cambio de contraseña obligatorio en primer login.
          navigate("/change-password");
        }
        // En ambos casos autenticados, dejamos de cargar.
        setIsLoading(false);
      } else {
        // No autenticado -> redirige a login.
        navigate("/login");
        setIsLoading(false);
      }
    } catch (err) {
      // Cualquier error de red u otro: tratamos como no autenticado por seguridad.
      // (Producción: opcionalmente loggear err)
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
      {/* IndexRegister consumirá la fecha del DateProvider (fijada a HOY) */}
      <IndexRegister />
    </div>
  );
};