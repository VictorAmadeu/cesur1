// C:\Proyectos\intranek\imports\ui\components\Layout\movil\NavMovil.jsx
// @ts-nocheck
//
// Barra de navegación MÓVIL (menú inferior + submenús).
// IMPORTANTE (Paso 2.3):
// - Este componente NO debe renderizar <Header /> (Header vive en MovilLayout).
// - Aquí dejamos solo navegación + badge + banner móvil.
//
// Además (Paso 2.2):
// - La navegación debe venir de navConfig.js (fuente única).

import React, { useEffect, useMemo, useState } from "react";
import { useNavigate } from "react-router-dom";

import { usePermissions } from "../../../../context/permissionsContext";
import LicenseService from "/imports/service/licenseService";

import {
  NAV_CONFIG,
  canAccess,
  buildBadgeCtx,
  getBadgeValue,
} from "../navConfig";

export default function NavMovil() {
  const navigate = useNavigate();

  // Controla visibilidad submenú "Tiempos"
  const [showTimeControlMenu, setShowTimeControlMenu] = useState(false);

  // Controla visibilidad submenú "Empleado"
  const [showEmployeeMenu, setShowEmployeeMenu] = useState(false);

  // Permisos y rol del usuario
  const { permissions, role } = usePermissions();

  // Pendientes de aprobación (badge + banner)
  const [pendingCount, setPendingCount] = useState(0);

  // Banner amarillo (cerrable)
  const [showPendingBanner, setShowPendingBanner] = useState(true);

  /**
   * Determina si el usuario actual puede aprobar ausencias:
   * supervisor, admin o super admin.
   */
  const isApprover = useMemo(() => {
    if (!role) return false;

    if (Array.isArray(role)) {
      return role.some(
        (r) =>
          String(r).includes("SUPERVISOR") ||
          String(r).includes("ADMIN") ||
          String(r).includes("SUPER_ADMIN")
      );
    }

    return (
      String(role).includes("SUPERVISOR") ||
      String(role).includes("ADMIN") ||
      String(role).includes("SUPER_ADMIN")
    );
  }, [role]);

  /**
   * Consulta resumen de pendientes (solo count).
   */
  const fetchPending = async () => {
    try {
      const res = await LicenseService.pendingSummary();
      setPendingCount(res?.code === 200 ? res?.count ?? 0 : 0);
    } catch (e) {
      // En caso de error, no rompemos UI
      setPendingCount(0);
    }
  };

  /**
   * Al montar (y si cambia el rol), si es aprobador consultamos pendientes.
   */
  useEffect(() => {
    if (isApprover) fetchPending();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [isApprover]);

  /**
   * Si cambia el número de pendientes y es > 0, volvemos a mostrar banner.
   */
  useEffect(() => {
    if (pendingCount > 0) setShowPendingBanner(true);
  }, [pendingCount]);

  /**
   * Alterna submenús (si abres uno, cierras el otro).
   * Usamos setState funcional para evitar estados inconsistentes.
   */
  const toggleMenu = (menu) => {
    if (menu === "time") {
      setShowTimeControlMenu((prev) => !prev);
      setShowEmployeeMenu(false);
      return;
    }

    if (menu === "employee") {
      setShowEmployeeMenu((prev) => !prev);
      setShowTimeControlMenu(false);
    }
  };

  /**
   * Navega y cierra submenús para evitar solapamientos.
   */
  const navigateAndCloseMenus = (path) => {
    setShowTimeControlMenu(false);
    setShowEmployeeMenu(false);
    navigate(path);
  };

  // Secciones base (por id) desde la configuración única
  const homeSection = NAV_CONFIG.find((s) => s.id === "home");
  const timeSection = NAV_CONFIG.find((s) => s.id === "time");
  const employeeSection = NAV_CONFIG.find((s) => s.id === "employee");
  const accountSection = NAV_CONFIG.find((s) => s.id === "account");

  // Contexto estándar para badges (navConfig).
  const badgeCtx = useMemo(
    () => buildBadgeCtx({ isApprover, pendingCount }),
    [isApprover, pendingCount]
  );

  // Badge en el icono "Empleado" (móvil)
  const employeeBadge = useMemo(() => {
    return getBadgeValue(employeeSection?.badge, badgeCtx);
  }, [employeeSection, badgeCtx]);

  // Submenú "Tiempos" filtrado por permisos
  const timeChildren = useMemo(() => {
    const children = Array.isArray(timeSection?.children)
      ? timeSection.children
      : [];

    return children.filter((c) => canAccess(permissions, c.permission));
  }, [timeSection, permissions]);

  // Submenú "Empleado" filtrado por permisos
  const employeeChildren = useMemo(() => {
    const children = Array.isArray(employeeSection?.children)
      ? employeeSection.children
      : [];

    return children.filter((c) => canAccess(permissions, c.permission));
  }, [employeeSection, permissions]);

  return (
    <div>
      {/* Barra inferior fija con iconos principales */}
      <nav
        className="navFooter"
        style={{
          boxShadow: showTimeControlMenu ? "0px -5px 13px 0px rgba(0,0,0,0.1)" : "none",
        }}
      >
        <ul className="lista">
          {/* Inicio */}
          <a
            href="#"
            onClick={(e) => {
              e.preventDefault();
              navigateAndCloseMenus(homeSection?.path ?? "/");
            }}
          >
            <li className="inicio">
              <i className={homeSection?.icon ?? "fa-solid fa-house"} />
              <p>{homeSection?.mobileLabel ?? "Inicio"}</p>
            </li>
          </a>

          {/* Tiempos (toggle) */}
          <a
            href="#"
            onClick={(e) => {
              e.preventDefault();
              toggleMenu("time");
            }}
          >
            <li className="informes">
              <i className={timeSection?.icon ?? "fa-solid fa-stopwatch"} />
              <p>{timeSection?.mobileLabel ?? "Tiempos"}</p>
            </li>
          </a>

          {/* Empleado (toggle + badge) */}
          <a
            href="#"
            onClick={(e) => {
              e.preventDefault();
              toggleMenu("employee");
            }}
          >
            <li className="descargas" style={{ position: "relative" }}>
              <i className={employeeSection?.icon ?? "fa-solid fa-user"} />
              <p>{employeeSection?.mobileLabel ?? "Empleado"}</p>

              {/* Badge rojo con el número de aprobaciones pendientes */}
              {employeeBadge > 0 ? (
                <span
                  style={{
                    position: "absolute",
                    top: "4px",
                    right: "12px",
                    background: "#d83737",
                    color: "#fff",
                    borderRadius: "9999px",
                    padding: "2px 6px",
                    fontSize: "11px",
                    fontWeight: "bold",
                  }}
                >
                  {employeeBadge}
                </span>
              ) : null}
            </li>
          </a>

          {/* Mi cuenta */}
          <a
            href="#"
            onClick={(e) => {
              e.preventDefault();
              navigateAndCloseMenus(accountSection?.path ?? "/perfil");
            }}
          >
            <li className="notificaciones">
              <i className={accountSection?.icon ?? "fa-solid fa-address-card"} />
              <p>{accountSection?.mobileLabel ?? "Mi cuenta"}</p>
            </li>
          </a>
        </ul>
      </nav>

      {/* Banner amarillo (solo aprobadores con pendientes) */}
      {isApprover && pendingCount > 0 && showPendingBanner ? (
        <div
          style={{
            position: "fixed",
            bottom: "72px",
            left: "12px",
            right: "12px",
            background: "#fff8e1",
            border: "1px solid #ffd54f",
            color: "#8a6d3b",
            padding: "8px 10px",
            borderRadius: "10px",
            zIndex: 1001,
            display: "flex",
            justifyContent: "space-between",
            alignItems: "center",
            gap: "10px",
          }}
        >
          <span>Tienes {pendingCount} aprobaciones pendientes.</span>

          <button
            type="button"
            onClick={() => setShowPendingBanner(false)}
            style={{
              background: "transparent",
              border: "none",
              color: "#8a6d3b",
              fontSize: "16px",
              fontWeight: "bold",
              cursor: "pointer",
              lineHeight: 1,
            }}
            aria-label="Cerrar aviso de aprobaciones pendientes"
            title="Cerrar"
          >
            ×
          </button>
        </div>
      ) : null}

      {/* Submenú de Tiempos */}
      {showTimeControlMenu ? (
        <div
          style={{
            display: "block",
            position: "fixed",
            bottom: 0,
            width: "100%",
            backgroundColor: "#1674a3",
            zIndex: 1000,
            marginBottom: "45px",
          }}
        >
          <ul
            className="lista"
            style={{
              display: "flex",
              justifyContent: "space-between",
              padding: "10px",
            }}
          >
            {timeChildren.map((item) => (
              <a
                key={item.id}
                href="#"
                onClick={(e) => {
                  e.preventDefault();
                  navigateAndCloseMenus(item.path);
                }}
                style={{ cursor: "pointer", color: "#fff" }}
              >
                <li className={item.id === "register-time" ? "Fichar" : "informes"}>
                  <p style={{ color: "#fff", fontSize: "14px" }}>{item.label}</p>
                </li>
              </a>
            ))}
          </ul>
        </div>
      ) : null}

      {/* Submenú de Empleado */}
      {showEmployeeMenu ? (
        <div className="fixed bottom-[45px] w-full bg-[#1674a3] z-[1000]">
          <ul className="flex justify-center gap-5 p-2">
            {employeeChildren.map((item) => (
              <a
                key={item.id}
                href="#"
                onClick={(e) => {
                  e.preventDefault();
                  navigateAndCloseMenus(item.path);
                }}
                className="flex items-center justify-center text-white text-center w-full no-underline"
              >
                <li className="informes" style={{ position: "relative" }}>
                  <p className="text-white text-sm">
                    {item.label}

                    {/* Badge opcional en items del submenú (ej. Ausencias en desktop; aquí lo dejamos soportado) */}
                    {getBadgeValue(item.badge, badgeCtx) > 0 ? (
                      <span
                        style={{
                          marginLeft: "6px",
                          background: "#d83737",
                          color: "#fff",
                          borderRadius: "9999px",
                          padding: "2px 6px",
                          fontSize: "11px",
                          fontWeight: "bold",
                        }}
                      >
                        {getBadgeValue(item.badge, badgeCtx)}
                      </span>
                    ) : null}
                  </p>
                </li>
              </a>
            ))}
          </ul>
        </div>
      ) : null}
    </div>
  );
}
