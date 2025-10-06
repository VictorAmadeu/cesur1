// imports/ui/components/Layout/movil/NavMovil.jsx
// @ts-nocheck
// Este fichero ya no contiene marcadores de conflicto. Se ha conservado
// la variante "jona" (rutas y textos) y se ha eliminado cualquier duplicación.

import React, { useEffect, useState } from 'react';
import { Header } from '../../Header/Header';
import { useNavigate } from 'react-router-dom';
import AuthService from '/imports/service/authService';
import { usePermissions } from '../../../../context/permissionsContext';

export default function NavMovil() {
  const navigate = useNavigate();
  const [showTimeControlMenu, setShowTimeControlMenu] = useState(false);
  const [controlMenu, setControlMenu] = useState(false);
  const { permissions } = usePermissions();

  // Alternar la visibilidad de los submenús (tiempos / empleado)
  const toggleMenu = (menu) => {
    if (menu === 'time') {
      setShowTimeControlMenu(!showTimeControlMenu);
      setControlMenu(false);
    } else if (menu === 'control') {
      setControlMenu(!controlMenu);
      setShowTimeControlMenu(false);
    }
  };

  // Cerrar menús y navegar a la ruta indicada
  const navigateAndCloseMenus = (path) => {
    setShowTimeControlMenu(false);
    setControlMenu(false);
    navigate(path);
  };

  return (
    <div>
      <nav
        className="navFooter"
        style={{
          boxShadow: showTimeControlMenu ? '0px -5px 13px 0px rgba(0,0,0,0.1)' : 'none'
        }}
      >
        <ul className="lista">
          <a onClick={() => navigateAndCloseMenus('/')}>
            <li className="inicio">
              <i className="fa-solid fa-house"></i>
              <p>Inicio</p>
            </li>
          </a>

        <a onClick={() => toggleMenu('time')}>
            <li className="informes">
              <i className="fa-solid fa-stopwatch"></i>
              <p>Tiempos</p>
            </li>
          </a>

          <a onClick={() => toggleMenu('control')}>
            <li className="descargas">
              <i className="fa-solid fa-user"></i>
              <p>Empleado</p>
            </li>
          </a>

          <a onClick={() => navigateAndCloseMenus('/perfil')}>
            <li className="notificaciones">
              <i className="fa-solid fa-address-card"></i>
              <p>Mi cuenta</p>
            </li>
          </a>
        </ul>
      </nav>

      {showTimeControlMenu && (
        <div
          style={{
            display: 'block',
            position: 'fixed',
            bottom: 0,
            width: '100%',
            backgroundColor: '#3a94cc',
            zIndex: 1000,
            marginBottom: '45px'
          }}
        >
          <ul
            className="lista"
            style={{
              display: 'flex',
              justifyContent: 'space-between',
              padding: '10px'
            }}
          >
            <a
              alt="Fichar"
              onClick={() => navigateAndCloseMenus('/registrar-tiempo')}
              style={{ cursor: 'pointer', color: '#fff' }}
            >
              <li className="Fichar">
                <p style={{ color: '#fff', fontSize: '14px' }}>Fichar</p>
              </li>
            </a>

            {/* variante jona: botón de justificación */}
            {permissions.applyAssignedSchedule && (
              <a
                alt="Justificar"
                onClick={() => navigateAndCloseMenus('/justification')}
                style={{ cursor: 'pointer', color: '#fff' }}
              >
                <li className="justificar">
                  <p style={{ color: '#fff', fontSize: '14px' }}>Justificar registros</p>
                </li>
              </a>
            )}

            <a
              alt="Mis tiempos"
              onClick={() => navigateAndCloseMenus('/ver-tiempo')}
              style={{ cursor: 'pointer', color: '#fff' }}
            >
              <li className="informes">
                <p style={{ color: '#fff', fontSize: '14px' }}>Mis tiempos</p>
              </li>
            </a>
          </ul>
        </div>
      )}

      {controlMenu && (
        <div className="fixed bottom-[45px] w-full bg-[#3a94cc] z-[1000]">
          <ul className="flex justify-center gap-5 p-2">
            {permissions.allowDocument && (
              <a
                onClick={() => navigateAndCloseMenus('/documentos')}
                className="flex items-center justify-center text-white text-center w-full no-underline"
              >
                <li className="informes">
                  <p className="text-white text-sm">Documentos</p>
                </li>
              </a>
            )}

            <a
              onClick={() => navigateAndCloseMenus('/ausencia')}
              className="flex items-center justify-center text-white text-center w-full no-underline"
            >
              <li className="informes">
                <p className="text-white text-sm">Ausencias</p>
              </li>
            </a>

            {permissions.allowWorkSchedule && (
              <a
                onClick={() => navigateAndCloseMenus('/horario')}
                className="flex items-center justify-center text-white text-center w-full no-underline"
              >
                <li className="informes">
                  <p className="text-white text-sm">Mi Horario</p>
                </li>
              </a>
            )}
          </ul>
        </div>
      )}
    </div>
  );
}
