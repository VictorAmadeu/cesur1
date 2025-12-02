// imports/ui/components/Home/NavigationHome.jsx

import React from "react";
import { useNavigate } from "react-router-dom";
import { usePermissions } from "../../../context/permissionsContext";

/**
 * NavigationHome
 * ---------------
 * Componente de la página de inicio que muestra los accesos rápidos:
 *  - Bloque "Control de tiempos"
 *  - Bloque "Rincón del empleado"
 */
export const NavigationHome = () => {
  // Hook de react-router para navegar entre pantallas sin recargar la página
  const navigate = useNavigate();

  // Obtenemos los permisos del usuario logado desde el contexto global
  const { permissions } = usePermissions();

  return (
    <div className="navigationHome">
      {/* Bloque de accesos de "Control de tiempos" */}
      <nav className="sectionCuadro">
        <h2>
          <i className="fa-solid fa-stopwatch"></i> Control de tiempos
        </h2>

        <ul className="lista">
          {/* Enlace para ir a la pantalla de fichar */}
          <li className="bgAzulO">
            <a
              onClick={() => navigate("/registrar-tiempo")}
              style={{ cursor: "pointer" }}
            >
              <p>Fichar</p>
            </a>
          </li>

          {/* Enlace para ver el historial de tiempos */}
          <li className="bgAzulO">
            <a
              onClick={() => navigate("/ver-tiempo")}
              style={{ cursor: "pointer" }}
            >
              <p>Historial de tiempos</p>
            </a>
          </li>
        </ul>
      </nav>

      {/* Bloque de accesos de "Rincón del empleado" */}
      <nav className="sectionCuadro">
        <h2>
          <i className="fa-solid fa-user"></i> Rincón del empleado
        </h2>

        <ul className="lista">
          {/* Enlace a Documentos, solo si el usuario tiene permiso */}
          {permissions.allowDocument && (
            <li className="bgAzulO">
              <a
                onClick={() => navigate("/documentos")}
                style={{ cursor: "pointer" }}
              >
                <p>Documentos</p>
              </a>
            </li>
          )}

          {/* Enlace a Ausencias: ruta corregida a /ausencia (singular) */}
          <li className="bgAzulO">
            <a
              onClick={() => navigate("/ausencia")}
              style={{ cursor: "pointer" }}
            >
              <p>Ausencias</p>
            </a>
          </li>
        </ul>
      </nav>
    </div>
  );
};
