// imports/ui/components/Header/Header.jsx

import React, { useEffect, useState } from "react";
// Componente que pinta el bloque de la derecha del header (avatar, menú, etc.)
import { LogoHeader } from "./LogoHeader";
// Servicios de backend
import CompanyService from "../../../service/companyService";
import AuthService from "../../../service/authService";
import UserService from "../../../service/userService";

/**
 * buildUserSummary
 * ----------------
 * A partir de la respuesta del perfil de usuario y de la compañía,
 * construye un objeto simple con:
 *  - employeeName: nombre completo del empleado
 *  - companyName: nombre de la empresa
 *
 * La función está preparada para que, si faltan datos, NO rompa nada
 * y simplemente devuelva cadenas vacías.
 */
const buildUserSummary = (profilePayload, companyPayload) => {
  // Normalizamos el posible formato de la respuesta:
  //  - a veces puede venir como array en data[0]
  //  - otras veces como objeto en data
  const normalizedData = Array.isArray(profilePayload?.data)
    ? profilePayload.data[0]
    : profilePayload?.data || {};

  // Construimos el nombre completo con las partes que existan
  const pieces = [
    normalizedData.name || normalizedData.firstname,
    normalizedData.lastname1,
    normalizedData.lastname2,
  ].filter(Boolean); // elimina undefined / null / ""

  const employeeName = (pieces.length > 0 ? pieces.join(" ") : "").trim();

  // Intentamos obtener el nombre de la empresa de distintos campos posibles
  const userCompany =
    normalizedData.company?.name ||
    normalizedData.company_name ||
    normalizedData.companyName ||
    "";

  return {
    employeeName,
    // Si no viene nada en el perfil, usamos el nombre de la empresa del logo
    companyName: (userCompany || companyPayload?.company_name || "").trim(),
  };
};

/**
 * Header
 * ------
 * Componente principal del header superior:
 *  - Carga logo y rol desde los servicios actuales.
 *  - Llama al perfil de usuario para obtener nombre y empresa.
 *  - Mientras carga, pinta un contenedor vacío con la altura del header.
 *  - Cuando termina, renderiza el logo de Intranek a la izquierda
 *    y el bloque <LogoHeader /> a la derecha.
 */
export const Header = () => {
  const [loading, setLoading] = useState(true); // Controla estado de carga
  const [logo, setLogo] = useState(); // Datos de logo y empresa
  const [role, setRole] = useState("ROLE_USER"); // Rol del usuario logueado
  const [userSummary, setUserSummary] = useState({
    employeeName: "",
    companyName: "",
  }); // Nombre de empleado + empresa

  // useEffect de montaje: se ejecuta una sola vez al cargar el componente
  useEffect(() => {
    loadHeaderData();
  }, []);

  /**
   * loadHeaderData
   * --------------
   * Recupera en paralelo:
   *  - Logo / datos de compañía
   *  - Perfil de usuario
   *  - Rol del usuario
   *
   * Con esa información actualiza el estado del header.
   */
  const loadHeaderData = async () => {
    try {
      setLoading(true);

      // Llamamos a logo y perfil en paralelo para ser más eficientes
      const [logoResponse, profileResponse] = await Promise.all([
        CompanyService.getLogo(),
        UserService.profile(),
      ]);

      // Guardamos logo (si viene vacío, dejamos cadena vacía para evitar errores)
      setLogo(logoResponse ?? "");
      // Obtenemos el rol usando el servicio ya existente
      setRole(AuthService.getRole());
      // Construimos y guardamos nombre de empleado + empresa
      setUserSummary(buildUserSummary(profileResponse, logoResponse));
    } catch (error) {
      // En caso de error, lo mostramos en consola para poder depurarlo
      // pero evitamos que la app se rompa.
      console.error("Error al cargar los datos del header", error);
    } finally {
      // Pase lo que pase (éxito o error), dejamos de estar en modo loading
      setLoading(false);
    }
  };

  return (
    <>
      {loading ? (
        // Mientras carga, dejamos reservada la altura del header
        <header className="headerMain">
          <div className="header h-[65px]"></div>
        </header>
      ) : (
        // Cuando ya tenemos la información, pintamos el header completo
        <header className="headerMain">
          <div className="header">
            {/* Logo fijo de Intranek a la izquierda */}
            <img className="logo" src="/images/general/logo.png" />

            {/* Bloque de la derecha: avatar + menú + (futuro) nombre/empresa */}
            <LogoHeader
              logo={logo}
              role={role}
              // Nuevos props para poder mostrar nombre y empresa al lado del icono
              employeeName={userSummary.employeeName}
              companyName={userSummary.companyName}
            />
          </div>
        </header>
      )}
    </>
  );
};
