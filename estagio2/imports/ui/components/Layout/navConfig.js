// imports/ui/components/Layout/navConfig.js
/**
 * Configuración única de navegación (Desktop + Móvil)
 *
 * Objetivo (Etapa 3 / Paso 2.2):
 * - Evitar duplicidad: la estructura del menú vive aquí.
 * - NavDesktop.jsx y NavMovil.jsx deben "mapear" este config.
 *
 * Importante (producción):
 * - Este archivo NO importa React, NO importa react-router-dom.
 * - Solo define datos + helpers puros (sin side effects).
 *
 * Permisos (deben coincidir con `permissions` de usePermissions()):
 * - applyAssignedSchedule
 * - allowDocument
 * - allowWorkSchedule
 *
 * Sobre el badge de pendientes:
 * - El fetch (LicenseService.pendingSummary) y el estado `pendingCount` viven en el Nav.
 * - Aquí solo definimos la regla de visualización usando un ctx estándar.
 */

/**
 * canAccess
 *
 * Evalúa si un item debe mostrarse según el objeto `permissions`.
 * - Si no hay permission => visible
 * - Si existe permission => visible solo si permissions[permission] es truthy
 */
export function canAccess(permissions, permission) {
  if (!permission) return true;
  return Boolean(permissions?.[permission]);
}

/**
 * buildBadgeCtx
 *
 * Contexto estándar para calcular badges.
 * El Nav (Desktop/Móvil) debería pasar:
 * - isApprover: boolean (según role)
 * - pendingCount: number (según LicenseService.pendingSummary)
 */
export function buildBadgeCtx({ isApprover = false, pendingCount = 0 } = {}) {
  return { isApprover, pendingCount };
}

/**
 * getBadgeValue
 *
 * Normaliza badge a número:
 * - Si badge es función => la ejecuta con `ctx`
 * - Si badge es número => lo devuelve
 * - Si no hay badge => 0
 */
export function getBadgeValue(badge, ctx) {
  if (!badge) return 0;
  if (typeof badge === "function") return Number(badge(ctx) ?? 0);
  if (typeof badge === "number") return badge;
  return 0;
}

/**
 * NAV_CONFIG
 *
 * Estructura común para representar:
 * - Menú superior Desktop (con submenús)
 * - Barra inferior Móvil (tabs) + submenús (Tiempos / Empleado)
 *
 * Campos mínimos por item (según guía Paso 2.2):
 * - path: ruta (si es clicable directamente)
 * - icon: clase de icono (FontAwesome)
 * - label: nombre visible (fallback)
 * - permission: permiso requerido (opcional)
 * - badge: función opcional (ctx => number)
 *
 * Notas:
 * - `desktopLabel` y `mobileLabel` respetan textos actuales.
 * - `children` representa los items del submenú.
 */
export const NAV_CONFIG = [
  {
    id: "home",
    path: "/",
    icon: "fa-solid fa-house",
    label: "Inicio",
    desktopLabel: "Inicio",
    mobileLabel: "Inicio",
  },

  {
    id: "time",
    icon: "fa-solid fa-stopwatch",
    label: "Control de tiempos",
    desktopLabel: "Control de tiempos",
    mobileLabel: "Tiempos",
    children: [
      {
        id: "register-time",
        path: "/registrar-tiempo",
        label: "Fichar",
      },
      {
        id: "justification",
        path: "/justification",
        label: "Justificar registros",
        permission: "applyAssignedSchedule",
      },
      {
        id: "my-times",
        path: "/ver-tiempo",
        label: "Mis tiempos",
      },
    ],
  },

  {
    id: "employee",
    icon: "fa-solid fa-user",
    label: "Rincón del empleado",
    desktopLabel: "Rincón del empleado",
    mobileLabel: "Empleado",

    // Badge en MÓVIL: se muestra en el icono "Empleado" solo si es aprobador y hay pendientes.
    badge: ({ isApprover, pendingCount }) => (isApprover ? pendingCount : 0),

    children: [
      {
        id: "documents",
        path: "/documentos",
        label: "Documentos",
        permission: "allowDocument",
      },
      {
        id: "absences",
        path: "/ausencia",
        label: "Ausencias",

        // Badge en DESKTOP: aparece al lado de "Ausencias" (submenú) solo si es aprobador y hay pendientes.
        badge: ({ isApprover, pendingCount }) => (isApprover ? pendingCount : 0),
      },
      {
        id: "schedule",
        path: "/horario",
        label: "Horario",
        permission: "allowWorkSchedule",
      },
    ],
  },

  {
    id: "account",
    path: "/perfil",
    icon: "fa-solid fa-address-card",
    label: "Mi cuenta",
    desktopLabel: "Mi cuenta",
    mobileLabel: "Mi cuenta",
  },
];
