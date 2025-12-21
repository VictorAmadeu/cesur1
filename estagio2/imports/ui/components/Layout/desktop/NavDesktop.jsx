// C:\Proyectos\intranek\imports\ui\components\Layout\desktop\NavDesktop.jsx
// @ts-nocheck
// Barra de navegacion de ESCRITORIO (version web/Skytop).

import React, { useEffect, useMemo, useState } from 'react';
import { Header } from '../../Header/Header';
import { useNavigate } from 'react-router-dom';
import { usePermissions } from '../../../../context/permissionsContext';
import LicenseService from '/imports/service/licenseService';
import { NAV_CONFIG, canAccess, buildBadgeCtx, getBadgeValue } from '../navConfig';

// Azul corporativo reutilizado de mobile (bgAzulO en client/css/home.css)
const BRAND_DARK_BLUE = '#1674a3';

// URL canonica del listado de Ausencias/Vacaciones en el ADMIN.
// Se usa tanto en el modal de pendientes como referencia centralizada.
const ADMIN_ABSENCES_URL =
  'https://admin.intranek.com/dashboard?crudControllerFqcn=App%5CController%5CAdmin%5CLicenseCrudController&crudAction=index';

/**
 * MenuItem
 * ----------
 * Boton principal del menu superior (Inicio, Control de tiempos, etc.).
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
      backgroundColor: BRAND_DARK_BLUE,
    }}
  >
    {icon && <i className={icon}></i>}
    <p>{label}</p>
  </button>
);

/**
 * SubMenuItem
 * ------------
 * Elemento individual dentro del submenu desplegable.
 * Puede mostrar un pequeno badge numerico (aprobaciones pendientes).
 */
const SubMenuItem = ({ label, onClick, badge }) => (
  <li
    style={{
      display: 'flex',
      width: '100%',
      justifyContent: 'left',
      position: 'relative',
    }}
  >
    <button
      onClick={onClick}
      style={{
        cursor: 'pointer',
        width: '100%',
        border: 'none',
        backgroundColor: BRAND_DARK_BLUE,
        paddingBlock: '8px',
      }}
    >
      <p
        style={{
          display: 'flex',
          alignItems: 'center',
          gap: '6px',
        }}
      >
        {label}
        {/* Badge rojo con el numero de aprobaciones pendientes */}
        {badge > 0 && (
          <span
            style={{
              background: '#d83737',
              color: '#fff',
              borderRadius: '9999px',
              padding: '2px 8px',
              fontSize: '11px',
              fontWeight: 'bold',
            }}
          >
            {badge}
          </span>
        )}
      </p>
    </button>
  </li>
);

/**
 * PendingBanner
 * ----------------
 * Tarjeta amarilla superior que avisa al supervisor/admin
 * de que tiene aprobaciones pendientes.
 * Solo se pinta si count > 0.
 */
const PendingBanner = ({ count, onView }) => {
  if (count <= 0) return null;

  return (
    <div
      style={{
        background: '#fff8e1',
        border: '1px solid #ffd54f',
        color: '#8a6d3b',
        padding: '10px 14px',
        borderRadius: '10px',
        display: 'flex',
        justifyContent: 'space-between',
        alignItems: 'center',
        margin: '10px 16px 0 16px',
      }}
    >
      <span>Tienes {count} aprobaciones pendientes.</span>
      <button
        onClick={onView}
        style={{
          background: '#3a94cc',
          color: '#fff',
          border: 'none',
          borderRadius: '6px',
          padding: '6px 10px',
          cursor: 'pointer',
        }}
      >
        Ver detalle
      </button>
    </div>
  );
};

/**
 * PendingModal
 * --------------
 * Modal sencillo que muestra un listado de aprobaciones pendientes
 * (tipo, empleado y rango de fechas) y un boton para abrir el Admin.
 */
const PendingModal = ({ open, onClose, items }) => {
  if (!open) return null;

  return (
    <div
      style={{
        position: 'fixed',
        inset: 0,
        background: 'rgba(0,0,0,0.35)',
        display: 'flex',
        justifyContent: 'center',
        alignItems: 'center',
        zIndex: 9999,
        padding: '16px',
      }}
    >
      <div
        style={{
          background: '#fff',
          borderRadius: '12px',
          padding: '16px',
          width: 'min(520px, 100%)',
          maxHeight: '80vh',
          overflowY: 'auto',
          boxShadow: '0 12px 32px rgba(0,0,0,0.2)',
        }}
      >
        {/* Cabecera del modal */}
        <div
          style={{
            display: 'flex',
            justifyContent: 'space-between',
            alignItems: 'center',
          }}
        >
          <h3 style={{ margin: 0 }}>Aprobaciones pendientes</h3>
          <button
            onClick={onClose}
            style={{
              background: 'transparent',
              border: 'none',
              fontSize: '18px',
              cursor: 'pointer',
            }}
            aria-label="Cerrar"
          >
            &times;
          </button>
        </div>

        {/* Contenido del modal: lista o mensaje vacio */}
        {items.length === 0 ? (
          <p style={{ marginTop: '12px' }}>Nada pendiente ahora mismo.</p>
        ) : (
          <div
            style={{
              marginTop: '12px',
              display: 'flex',
              flexDirection: 'column',
              gap: '10px',
            }}
          >
            {items.map((item) => (
              <div
                key={item.id}
                style={{
                  border: '1px solid #e5e7eb',
                  borderRadius: '8px',
                  padding: '10px',
                  display: 'flex',
                  flexDirection: 'column',
                  gap: '4px',
                }}
              >
                <strong>{item.type}</strong>
                <span>{item.userName}</span>
                <span>
                  {item.dateStart} - {item.dateEnd}
                </span>
              </div>
            ))}
          </div>
        )}

        {/* Zona de acciones del modal */}
        <div
          style={{
            marginTop: '14px',
            display: 'flex',
            gap: '8px',
            justifyContent: 'flex-end',
          }}
        >
          <button
            // Abrimos directamente el listado de Ausencias/Vacaciones en el Admin
            onClick={() =>
              window.open(
                ADMIN_ABSENCES_URL,
                '_blank',
                'noopener noreferrer'
              )
            }
            style={{
              background: '#3a94cc',
              color: '#fff',
              border: 'none',
              borderRadius: '6px',
              padding: '8px 12px',
              cursor: 'pointer',
            }}
          >
            Abrir Admin
          </button>
          <button
            onClick={onClose}
            style={{
              background: '#e5e7eb',
              color: '#111',
              border: 'none',
              borderRadius: '6px',
              padding: '8px 12px',
              cursor: 'pointer',
            }}
          >
            Cerrar
          </button>
        </div>
      </div>
    </div>
  );
};

/**
 * NavDesktop
 * -----------
 * Barra de navegacion de la version web/escritorio.
 *
 * - Muestra el Header (logo + bloque usuario).
 * - Pinta el menu superior con el azul corporativo.
 * - Para roles supervisor/admin:
 *   - Consulta aprobaciones pendientes al cargar.
 *   - Muestra banner superior y badge en "Ausencias".
 */
const NavDesktop = () => {
  const navigate = useNavigate();
  const [activeSubMenu, setActiveSubMenu] = useState(null);

  // permissions: flags de lo que puede ver el usuario
  // role: rol del usuario (empleado, supervisor, admin, etc.)
  const { permissions, role } = usePermissions();

  // Numero total de aprobaciones pendientes
  const [pendingCount, setPendingCount] = useState(0);

  // Lista corta de pendientes para mostrar en el modal
  const [pendingList, setPendingList] = useState([]);

  // Controla si el modal esta abierto o cerrado
  const [showModal, setShowModal] = useState(false);

  /**
   * Determina si el usuario actual es un aprobador
   * (supervisor, admin o super admin).
   */
  const isApprover = useMemo(() => {
    if (!role) return false;

    // Algunos proyectos guardan el rol como array; lo cubrimos por seguridad.
    if (Array.isArray(role)) {
      return role.some(
        (r) =>
          String(r).includes('SUPERVISOR') ||
          String(r).includes('ADMIN') ||
          String(r).includes('SUPER_ADMIN')
      );
    }

    // Caso normal: rol como string
    return (
      String(role).includes('SUPERVISOR') ||
      String(role).includes('ADMIN') ||
      String(role).includes('SUPER_ADMIN')
    );
  }, [role]);

  /**
   * Llama a la API /license/pending-summary para
   * obtener el numero de aprobaciones pendientes y
   * una lista resumida.
   */
  const fetchPending = async () => {
    try {
      const res = await LicenseService.pendingSummary();

      if (res?.code === 200) {
        setPendingCount(res.count || 0);
        setPendingList(res.list || []);
      } else {
        setPendingCount(0);
        setPendingList([]);
      }
    } catch (e) {
      // En caso de error de red, dejamos contador a 0
      setPendingCount(0);
      setPendingList([]);
    }
  };

  /**
   * useEffect
   * ----------
   * Al montar el componente (y cuando cambie el rol),
   * si el usuario es aprobador se consultan los pendientes.
   */
  useEffect(() => {
    if (isApprover) {
      fetchPending();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [isApprover]);

  // Contexto estandar para badges (navConfig).
  const badgeCtx = useMemo(
    () => buildBadgeCtx({ isApprover, pendingCount }),
    [isApprover, pendingCount]
  );

  // Definicion de las secciones que aparecen en la barra de navegacion superior.
  const routes = useMemo(() => {
    return NAV_CONFIG.filter((section) => canAccess(permissions, section.permission)).map(
      (section) => {
        const submenu =
          Array.isArray(section.children) && section.children.length > 0
            ? section.children
                .filter((child) => canAccess(permissions, child.permission))
                .map((child) => ({
                  path: child.path,
                  label: child.label,
                  onClick: () => navigate(child.path),
                  badge: getBadgeValue(child.badge, badgeCtx),
                }))
            : null;

        return {
          path: section.path,
          label: section.desktopLabel || section.label,
          icon: section.icon,
          onClick: section.path ? () => navigate(section.path) : undefined,
          submenu,
        };
      }
    );
  }, [permissions, navigate, badgeCtx]);

  // Abre el submenu cuando el raton entra en un elemento del menu.
  const handleMouseEnter = (label) => {
    setActiveSubMenu(label);
  };

  // Cierra el submenu cuando el raton sale del elemento del menu.
  const handleMouseLeave = () => {
    setActiveSubMenu(null);
  };

  return (
    <div>
      {/* Cabecera superior: logo de Intranek + bloque de usuario */}
      <Header />

      {/* Banner de aviso si hay pendientes */}
      {isApprover && (
        <PendingBanner
          count={pendingCount}
          onView={() => setShowModal(true)}
        />
      )}

      {/* Barra de navegacion de escritorio.
          IMPORTANTE: el fondo se fuerza al azul oscuro corporativo
          para alinearlo con los menus desplegables de movil. */}
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

                  {/* Submenu desplegable, mismo azul oscuro que la barra */}
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
                          badge={submenuItem.badge || 0}
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

      {/* Modal de pendientes (solo se renderiza cuando showModal es true) */}
      <PendingModal
        open={showModal}
        onClose={() => setShowModal(false)}
        items={pendingList}
      />
    </div>
  );
};

export default NavDesktop;
