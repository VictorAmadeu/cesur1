import React from "react";
import { useNavigate } from "react-router-dom";
import { usePermissions } from "../../../context/permissionsContext";

export const NavigationHome = () => {
  const navigate = useNavigate();
  const { permissions } = usePermissions()

  return (
    <div className="navigationHome">
      <nav className="sectionCuadro">
        <h2>
          <i className="fa-solid fa-stopwatch"></i> Control de tiempos
        </h2>
        <ul className="lista">
          <li className="bgAzulO">
            <a
              onClick={() => navigate("/registrar-tiempo")}
              alt="registrar tiempo"
              style={{ cursor: "pointer" }}
            >
              <p>Fichar</p>
            </a>
          </li>
          <li className="bgAzulO">
            <a
              onClick={() => navigate("/ver-tiempo")}
              alt="Historial"
              style={{ cursor: "pointer" }}
            >
              <p>Historial de tiempos</p>
            </a>
          </li>
        </ul>
      </nav>
      <nav className="sectionCuadro">
        <h2>
          <i className="fa-solid fa-user"></i> Rincón del empleado
        </h2>
        <ul className="lista">
          {permissions.allowDocument ? (
            <li className="bgAzulO">
              <a
                onClick={() => navigate("/documentos")}
                alt="Documentos"
                style={{ cursor: "pointer" }}
              >
                <p>Documentos</p>
              </a>
            </li>
          ) : null}
          <li className="bgAzulO">
            <a
              onClick={() => navigate("/ausencias")}
              alt="Ausencias"
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
