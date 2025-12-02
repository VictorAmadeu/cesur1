// imports/ui/components/Layout/movil/NavMovil.jsx
// @ts-nocheck

// Importamos React y los hooks necesarios
import React, { useEffect, useState } from 'react';
// Header se usa en otros layouts; aquí se mantiene por consistencia del proyecto
import { Header } from '../../Header/Header';
// Hook de React Router para navegar entre pantallas
import { useNavigate } from 'react-router-dom';
// Servicio de autenticación (aunque no se use directamente aquí, se mantiene por diseño actual)
import AuthService from '/imports/service/authService';
// Hook de contexto para obtener los permisos del usuario logado
import { usePermissions } from '../../../../context/permissionsContext';
// Servicio de licencias/ausencias (usado para consultar aprobaciones pendientes)
import LicenseService from '/imports/service/licenseService';

export default function NavMovil() {
  // Hook para navegar a otras rutas
  const navigate = useNavigate();

  // Estado que controla si el submenú de "Tiempos" está visible o no
  const [showTimeControlMenu, setShowTimeControlMenu] = useState(false);

  // Estado que controla si el submenú de "Empleado" está visible o no
  const [controlMenu, setControlMenu] = useState(false);

  // Obtenemos permisos y rol del usuario desde el contexto global
  const { permissions, role } = usePermissions();

  // Número total de aprobaciones pendientes para este usuario (si es supervisor/admin)
  const [pendingCount, setPendingCount] = useState(0);

  // Controla si el banner amarillo de pendientes se muestra o no.
  // Permite que el usuario pueda cerrarlo pulsando en la "x".
  const [showPendingBanner, setShowPendingBanner] = useState(true);

  /**
   * isApprover
   * ----------
   * Determina si el usuario actual puede aprobar ausencias:
   * supervisor, admin o super admin.
   */
  const isApprover = (() => {
    if (!role) return false;

    // Por seguridad, contemplamos que `role` pueda ser array o string
    if (Array.isArray(role)) {
      return role.some(
        (r) => r.includes('SUPERVISOR') || r.includes('ADMIN')
      );
    }

    // Caso normal: rol como string
    return (
      role.includes('SUPERVISOR') ||
      role.includes('ADMIN') ||
      role.includes('SUPER_ADMIN')
    );
  })();

  /**
   * fetchPending
   * ------------
   * Llama al backend para obtener un resumen de aprobaciones pendientes
   * (solo número total). Se usa en el badge y en el banner.
   */
  const fetchPending = async () => {
    try {
      const res = await LicenseService.pendingSummary();

      if (res?.code === 200) {
        setPendingCount(res.count || 0);
      } else {
        setPendingCount(0);
      }
    } catch (e) {
      // Si hay error de red o backend, no rompemos la app: dejamos el contador a 0
      setPendingCount(0);
    }
  };

  /**
   * useEffect
   * ----------
   * Al montar el componente (y si cambia el rol),
   * si el usuario es aprobador se consultan los pendientes.
   */
  useEffect(() => {
    if (isApprover) {
      fetchPending();
    }
    // Dependemos de isApprover; si cambia, se vuelve a consultar.
  }, [isApprover]);

  /**
   * useEffect
   * ----------
   * Si cambia el número de pendientes y es mayor que 0,
   * volvemos a mostrar el banner (por ejemplo, tras refrescar datos).
   */
  useEffect(() => {
    if (pendingCount > 0) {
      setShowPendingBanner(true);
    }
  }, [pendingCount]);

  // Función que alterna la visibilidad de los submenús
  // Si se abre un submenú, el otro se cierra para evitar solapamientos
  const toggleMenu = (menu) => {
    if (menu === 'time') {
      setShowTimeControlMenu(!showTimeControlMenu);
      setControlMenu(false); // Cierra el submenú de empleado
    } else if (menu === 'control') {
      setControlMenu(!controlMenu);
      setShowTimeControlMenu(false); // Cierra el submenú de tiempos
    }
  };

  // Función auxiliar que navega a una ruta y cierra cualquier submenú abierto
  const navigateAndCloseMenus = (path) => {
    setShowTimeControlMenu(false);
    setControlMenu(false);
    navigate(path);
  };

  // Render del menú inferior móvil y sus submenús
  return (
    <div>
      {/* Barra de navegación inferior fija con los iconos principales */}
      <nav
        className="navFooter"
        style={{
          // Añade una sombra suave cuando el submenú de tiempos está abierto
          boxShadow: showTimeControlMenu
            ? '0px -5px 13px 0px rgba(0,0,0,0.1)'
            : 'none',
        }}
      >
        <ul className="lista">
          {/* Botón Inicio: lleva a la página principal y cierra submenús */}
          <a onClick={() => navigateAndCloseMenus('/')}>
            <li className="inicio">
              <i className="fa-solid fa-house" />
              <p>Inicio</p>
            </li>
          </a>

          {/* Botón Tiempos: despliega o esconde el submenú de control de tiempos */}
          <a onClick={() => toggleMenu('time')}>
            <li className="informes">
              <i className="fa-solid fa-stopwatch" />
              <p>Tiempos</p>
            </li>
          </a>

          {/* Botón Empleado: despliega o esconde el submenú de empleado */}
          <a onClick={() => toggleMenu('control')}>
            <li
              className="descargas"
              // Necesario para posicionar correctamente el badge rojo
              style={{ position: 'relative' }}
            >
              <i className="fa-solid fa-user" />
              <p>Empleado</p>

              {/* Badge rojo con el número de aprobaciones pendientes (solo supervisores/admin) */}
              {isApprover && pendingCount > 0 && (
                <span
                  style={{
                    position: 'absolute',
                    top: '4px',
                    right: '12px',
                    background: '#d83737',
                    color: '#fff',
                    borderRadius: '9999px',
                    padding: '2px 6px',
                    fontSize: '11px',
                    fontWeight: 'bold',
                  }}
                >
                  {pendingCount}
                </span>
              )}
            </li>
          </a>

          {/* Botón Mi cuenta: va al perfil del usuario y cierra submenús */}
          <a onClick={() => navigateAndCloseMenus('/perfil')}>
            <li className="notificaciones">
              <i className="fa-solid fa-address-card" />
              <p>Mi cuenta</p>
            </li>
          </a>
        </ul>
      </nav>

      {/* Banner de aviso en móvil si hay pendientes y el usuario es aprobador
          AHORA: sin botón "Abrir" y con una "x" para cerrarlo. */}
      {isApprover && pendingCount > 0 && showPendingBanner && (
        <div
          style={{
            position: 'fixed',
            bottom: '72px', // justo por encima de la barra inferior
            left: '12px',
            right: '12px',
            background: '#fff8e1',
            border: '1px solid #ffd54f',
            color: '#8a6d3b',
            padding: '8px 10px',
            borderRadius: '10px',
            zIndex: 1001,
            display: 'flex',
            justifyContent: 'space-between',
            alignItems: 'center',
            gap: '10px',
          }}
        >
          <span>Tienes {pendingCount} aprobaciones pendientes.</span>
          {/* Botón para cerrar el banner de aviso */}
          <button
            type="button"
            onClick={() => setShowPendingBanner(false)}
            style={{
              background: 'transparent',
              border: 'none',
              color: '#8a6d3b',
              fontSize: '16px',
              fontWeight: 'bold',
              cursor: 'pointer',
              lineHeight: 1,
            }}
            aria-label="Cerrar aviso de aprobaciones pendientes"
          >
            ×
          </button>
        </div>
      )}

      {/* Submenú de Tiempos: aparece al pulsar "Tiempos" */}
      {showTimeControlMenu && (
        <div
          style={{
            display: 'block',
            position: 'fixed',
            bottom: 0,
            width: '100%',
            // Color de fondo actualizado al azul corporativo oscuro (#1674a3)
            backgroundColor: '#1674a3',
            zIndex: 1000,
            // Se deja un margen para no solaparse con la barra inferior
            marginBottom: '45px',
          }}
        >
          <ul
            className="lista"
            style={{
              display: 'flex',
              justifyContent: 'space-between',
              padding: '10px',
            }}
          >
            {/* Acción Fichar: registro rápido de tiempo */}
            <a
              alt="Fichar"
              onClick={() => navigateAndCloseMenus('/registrar-tiempo')}
              style={{ cursor: 'pointer', color: '#fff' }}
            >
              <li className="Fichar">
                <p style={{ color: '#fff', fontSize: '14px' }}>Fichar</p>
              </li>
            </a>

            {/* Acción Justificar registros: solo visible si el usuario tiene el permiso */}
            {permissions.applyAssignedSchedule && (
              <a
                alt="Justificar"
                onClick={() => navigateAndCloseMenus('/justification')}
                style={{ cursor: 'pointer', color: '#fff' }}
              >
                <li className="justificar">
                  <p style={{ color: '#fff', fontSize: '14px' }}>
                    Justificar registros
                  </p>
                </li>
              </a>
            )}

            {/* Acción Mis tiempos: acceso al listado de fichajes */}
            <a
              alt="Mis tiempos"
              onClick={() => navigateAndCloseMenus('/ver-tiempo')}
              style={{ cursor: 'pointer', color: '#fff' }}
            >
              <li className="informes">
                <p style={{ color: '#fff', fontSize: '14px' }}>
                  Mis tiempos
                </p>
              </li>
            </a>
          </ul>
        </div>
      )}

      {/* Submenú de Empleado: aparece al pulsar "Empleado" */}
      {controlMenu && (
        // Contenedor fijo del submenú de empleado con el nuevo color azul oscuro
        <div className="fixed bottom-[45px] w-full bg-[#1674a3] z-[1000]">
          <ul className="flex justify-center gap-5 p-2">
            {/* Opción Documentos: solo si el usuario tiene permiso para ver documentos */}
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

            {/* Opción Ausencias: acceso a la gestión de ausencias */}
            <a
              onClick={() => navigateAndCloseMenus('/ausencia')}
              className="flex items-center justify-center text-white text-center w-full no-underline"
            >
              <li className="informes">
                <p className="text-white text-sm">Ausencias</p>
              </li>
            </a>

            {/* Opción Mi Horario: solo si el usuario puede ver su horario de trabajo */}
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
