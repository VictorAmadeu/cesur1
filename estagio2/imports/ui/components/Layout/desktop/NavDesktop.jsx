// C:\Proyectos\intranek\imports\ui\components\Layout\desktop\NavDesktop.jsx
// @ts-nocheck
// Barra de navegación de ESCRITORIO (versión web/Skytop).

import React, { useState } from 'react';
import { Header } from '../../Header/Header';
import { useNavigate } from 'react-router-dom';
import { usePermissions } from '../../../../context/permissionsContext';

// 🎨 Azul oscuro corporativo reutilizado de mobile (bgAzulO en client/css/home.css)
const BRAND_DARK_BLUE = '#1674a3';

/**
 * MenuItem
 * ----------
 * Botón principal del menú superior (Inicio, Control de tiempos, etc.).
 * Se pinta como <button> para poder gestionar hover y click en escritorio.
 */
const MenuItem = ({ label, icon, onClick, onMouseEnter }) => (
  <button
    onMouseEnter={onMouseEnter}
    onClick={onClick}
    style={{
      cursor: 'pointer',
      display: 'flex',
      alignContent: 'center',
      alignItems: 'center',
      width: '100%',
      border: 'none',
      backgroundColor: BRAND_DARK_BLUE
    }}
  >
    {icon && <i className={icon}></i>}
    <p>{label}</p>
  </button>
);

/**
 * SubMenuItem
 * ------------
 * Elemento individual dentro del submenú desplegable.
 */
const SubMenuItem = ({ label, onClick }) => (
  <li style={{ display: 'flex', width: '100%', justifyContent: 'left' }}>
    <button
      onClick={onClick}
      style={{
        cursor: 'pointer',
        width: '100%',
        border: 'none',
        backgroundColor: BRAND_DARK_BLUE,
        paddingBlock: '8px'
      }}
    >
      <p>{label}</p>
    </button>
  </li>
);

/**
 * NavDesktop
 * -----------
 * Barra de navegación de la versión web/escritorio.
 * - Muestra el Header (logo + bloque usuario).
 * - Pinta el menú superior con el mismo azul oscuro que se usa en móvil.
 */
const NavDesktop = () => {
  const navigate = useNavigate();
  const [activeSubMenu, setActiveSubMenu] = useState(null);
  const { permissions } = usePermissions();

  // Abre el submenú cuando el ratón entra en un elemento del menú.
  const handleMouseEnter = (label) => {
    setActiveSubMenu(label);
  };

  // Cierra el submenú cuando el ratón sale del elemento del menú.
  const handleMouseLeave = () => {
    setActiveSubMenu(null);
  };

  // Definición de las secciones que aparecen en la barra de navegación superior.
  const routes = [
    {
      path: '/',
      label: 'Inicio',
      icon: 'fa-solid fa-house',
      onClick: () => navigate('/')
    },
    {
      label: 'Control de tiempos',
      icon: 'fa-solid fa-stopwatch',
      submenu: [
        {
          path: '/registrar-tiempo',
          label: 'Fichar',
          onClick: () => navigate('/registrar-tiempo')
        },
        ...(permissions.applyAssignedSchedule
          ? [
              {
                path: '/justification',
                label: 'Justificar registros',
                onClick: () => navigate('/justification')
              }
            ]
          : []),
        {
          path: '/ver-tiempo',
          label: 'Mis tiempos',
          onClick: () => navigate('/ver-tiempo')
        }
      ]
    },
    {
      label: 'Rincón del empleado',
      icon: 'fa-solid fa-user',
      submenu: [
        ...(permissions.allowDocument
          ? [
              {
                path: '/documentos',
                label: 'Documentos',
                onClick: () => navigate('/documentos')
              }
            ]
          : []),
        {
          path: '/ausencia',
          label: 'Ausencias',
          onClick: () => navigate('/ausencia')
        },
        ...(permissions.allowWorkSchedule
          ? [
              {
                path: '/horario',
                label: 'Horario',
                onClick: () => navigate('/horario')
              }
            ]
          : [])
      ]
    },
    {
      path: '/perfil',
      label: 'Mi cuenta',
      icon: 'fa-solid fa-address-card',
      onClick: () => navigate('/perfil')
    }
  ];

  return (
    <div>
      {/* Cabecera superior: logo de Intranek + bloque de usuario */}
      <Header />

      {/* Barra de navegación de escritorio.
          IMPORTANTE: el fondo se fuerza al azul oscuro corporativo
          para alinearlo con los menús desplegables de móvil. */}
      <nav className="navPC" style={{ backgroundColor: BRAND_DARK_BLUE }}>
        <ul className="lista">
          {routes.map((route, index) => (
            <li
              key={index}
              className="inicio"
              onMouseLeave={handleMouseLeave}
            >
              {route.submenu ? (
                <div
                  onMouseEnter={() => handleMouseEnter(route.label)}
                  className="relative"
                >
                  <MenuItem
                    label={route.label}
                    icon={route.icon}
                    onClick={route.onClick}
                  />

                  {/* Submenú desplegable, mismo azul oscuro que la barra */}
                  {activeSubMenu === route.label && (
                    <ul
                      className="absolute top-full left-0 w-full flex flex-col items-start p-0 z-[9999]"
                      style={{ backgroundColor: BRAND_DARK_BLUE }}
                    >
                      {route.submenu.map((submenuItem, subIndex) => (
                        <SubMenuItem
                          key={subIndex}
                          label={submenuItem.label}
                          onClick={submenuItem.onClick}
                        />
                      ))}
                    </ul>
                  )}
                </div>
              ) : (
                <MenuItem
                  label={route.label}
                  icon={route.icon}
                  onClick={route.onClick}
                />
              )}
            </li>
          ))}
        </ul>
      </nav>
    </div>
  );
};

export default NavDesktop;
