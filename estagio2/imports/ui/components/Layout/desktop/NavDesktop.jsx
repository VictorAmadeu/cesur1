// @ts-nocheck
import React, { useEffect, useState } from 'react';
import { Header } from '../../Header/Header';
import { useNavigate } from 'react-router-dom';
import AuthService from '/imports/service/authService';
import { usePermissions } from '../../../../context/permissionsContext';

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
      backgroundColor: '#3a94cc'
    }}
  >
    {icon && <i className={icon}></i>}
    <p>{label}</p>
  </button>
);

const SubMenuItem = ({ label, onClick }) => (
  <li style={{ display: 'flex', width: '100%', justifyContent: 'left' }}>
    <button
      onClick={onClick}
      style={{
        cursor: 'pointer',
        width: '100%',
        border: 'none',
        backgroundColor: '#3a94cc',
        paddingBlock: '8px'
      }}
    >
      <p>{label}</p>
    </button>
  </li>
);

const NavDesktop = () => {
  const navigate = useNavigate();
  const [activeSubMenu, setActiveSubMenu] = useState(null);
  const { permissions } = usePermissions();

  const handleMouseEnter = (label) => {
    setActiveSubMenu(label);
  };

  const handleMouseLeave = () => {
    setActiveSubMenu(null);
  };

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
      <Header />
      <nav className="navPC">
        <ul className="lista">
          {routes.map((route, index) => (
            <li key={index} className="inicio">
              {route.submenu ? (
                <div
                  onMouseEnter={() => handleMouseEnter(route.label)}
                  onMouseLeave={handleMouseLeave}
                  className="relative"
                >
                  <MenuItem label={route.label} icon={route.icon} onClick={route.onClick} />
                  {activeSubMenu === route.label && (
                    <ul className="absolute top-full left-0 w-full flex flex-col items-start bg-[#3a94cc] p-0 z-[9999]">
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
                <MenuItem label={route.label} icon={route.icon} onClick={route.onClick} />
              )}
            </li>
          ))}
        </ul>
      </nav>
    </div>
  );
};

export default NavDesktop;
