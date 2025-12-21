// @ts-nocheck
/**
 * deviceUtils.js (UNIFICADO web + móvil/Cordova)
 * -------------------------------------------------------------------
 * Objetivo:
 * - Unificar la obtención/creación de deviceId en una sola utilidad.
 * - Mantener COMPATIBILIDAD con:
 *   - Mobile: getDeviceId(), createAndStoreDeviceId()
 *   - Desktop: getStoredDeviceId() -> { code, status, deviceId, source }
 *
 * Nota de producción (muy importante):
 * - Aquí NO cambiamos reglas de negocio del backend.
 * - Solo normalizamos cómo obtenemos/creamos/persistimos deviceId en cliente.
 * - Evitamos romper imports existentes manteniendo nombres legacy.
 */

import { v4 as uuidV4 } from "uuid"; // Renombrado para evitar colisiones
import localforage from "localforage";
import Cookies from "js-cookie";
import { Meteor } from "meteor/meteor";

const DEVICE_KEY = "deviceId"; // clave principal (localforage / NativeStorage)
const DEVICE_BACKUP_KEY = "deviceId_backup"; // backups (cookies/localStorage/sessionStorage)

// Logging controlado (por defecto apagado para no ensuciar consola en producción)
const DEBUG = false;
const log = (...args) => {
  if (DEBUG) console.log("[deviceUtils]", ...args);
};

const isCordovaWithNativeStorage = () => {
  return Boolean(
    Meteor &&
      Meteor.isCordova &&
      typeof window !== "undefined" &&
      window.NativeStorage
  );
};

/**
 * Lectura segura de localStorage/sessionStorage/cookies (evita romper en entornos restringidos)
 */
const safeLocalStorageGet = (key) => {
  try {
    return localStorage.getItem(key);
  } catch {
    return null;
  }
};

const safeSessionStorageGet = (key) => {
  try {
    return sessionStorage.getItem(key);
  } catch {
    return null;
  }
};

const safeLocalStorageSet = (key, value) => {
  try {
    localStorage.setItem(key, value);
  } catch {
    // Ignorar: no queremos romper producción si el storage está bloqueado
  }
};

const safeSessionStorageSet = (key, value) => {
  try {
    sessionStorage.setItem(key, value);
  } catch {
    // Ignorar
  }
};

const safeCookieGet = (key) => {
  try {
    // js-cookie usa document.cookie, puede fallar si el entorno lo restringe
    return Cookies.get(key) || null;
  } catch {
    return null;
  }
};

const safeCookieSet = (key, value) => {
  try {
    Cookies.set(key, value);
  } catch {
    // Ignorar
  }
};

const safeCookieRemove = (key) => {
  try {
    Cookies.remove(key);
  } catch {
    // Ignorar
  }
};

/**
 * NativeStorage helpers (Cordova)
 */
const nativeGetItem = (key) => {
  return new Promise((resolve) => {
    if (!isCordovaWithNativeStorage()) return resolve(null);

    window.NativeStorage.getItem(
      key,
      (value) => resolve(value || null),
      () => resolve(null)
    );
  });
};

const nativeSetItem = (key, value) => {
  return new Promise((resolve, reject) => {
    if (!isCordovaWithNativeStorage()) return resolve();

    window.NativeStorage.setItem(
      key,
      value,
      () => resolve(),
      (err) => reject(err)
    );
  });
};

const nativeRemoveItem = (key) => {
  return new Promise((resolve) => {
    if (!isCordovaWithNativeStorage()) return resolve();

    // El plugin suele exponer "remove". Si no existiera, intentamos "removeItem".
    const ns = window.NativeStorage;
    const remover = ns.remove
      ? ns.remove.bind(ns)
      : (ns.removeItem ? ns.removeItem.bind(ns) : null);

    if (!remover) return resolve();

    remover(
      key,
      () => resolve(),
      () => resolve()
    );
  });
};

/**
 * Busca deviceId en orden CONSERVADOR (basado en lo que ya hace desktop),
 * añadiendo Cordova/NativeStorage por delante.
 *
 * @returns {Promise<{ deviceId: string|null, source: string|null }>}
 */
const findStoredDeviceIdDetailed = async () => {
  // 1) Cordova: NativeStorage (si existe)
  if (isCordovaWithNativeStorage()) {
    const fromNative = await nativeGetItem(DEVICE_KEY);
    if (fromNative) return { deviceId: fromNative, source: "NativeStorage" };
  }

  // 2) localforage (ambos entornos como fallback robusto)
  try {
    const fromLocalForage = await localforage.getItem(DEVICE_KEY);
    if (fromLocalForage) return { deviceId: fromLocalForage, source: "localforage" };
  } catch {
    // Ignorar: no rompemos si IndexedDB/localforage falla
  }

  // 3) backups (igual que desktop, sin alterar el contrato)
  const fromCookies = safeCookieGet(DEVICE_BACKUP_KEY);
  if (fromCookies) return { deviceId: fromCookies, source: "cookies" };

  const fromLocalStorage = safeLocalStorageGet(DEVICE_BACKUP_KEY);
  if (fromLocalStorage) return { deviceId: fromLocalStorage, source: "localStorage" };

  const fromSessionStorage = safeSessionStorageGet(DEVICE_BACKUP_KEY);
  if (fromSessionStorage) return { deviceId: fromSessionStorage, source: "sessionStorage" };

  return { deviceId: null, source: null };
};

/**
 * Persiste deviceId en los almacenamientos disponibles.
 * Importante:
 * - No fallamos si alguno no está disponible (producción).
 * - En Cordova intentamos NativeStorage + localforage (fallback).
 * - En Web intentamos localforage + backups (cookie/localStorage/sessionStorage).
 */
const persistDeviceId = async (deviceId) => {
  if (!deviceId) return;

  // 1) Cordova NativeStorage (si aplica)
  if (isCordovaWithNativeStorage()) {
    try {
      await nativeSetItem(DEVICE_KEY, deviceId);
    } catch {
      // Ignorar: seguiremos intentando otros stores
    }
  }

  // 2) localforage (fallback robusto para ambos)
  try {
    await localforage.setItem(DEVICE_KEY, deviceId);
  } catch {
    // Ignorar
  }

  // 3) backups web (no rompen si no existen)
  safeCookieSet(DEVICE_BACKUP_KEY, deviceId);
  safeLocalStorageSet(DEVICE_BACKUP_KEY, deviceId);
  safeSessionStorageSet(DEVICE_BACKUP_KEY, deviceId);
};

/**
 * API MOBILE LEGACY (no rompe):
 * - Devuelve deviceId si existe, o null si no existe.
 * (Esto respeta el comportamiento actual de móvil.)
 */
export const getDeviceId = async () => {
  const found = await findStoredDeviceIdDetailed();
  return found.deviceId || null;
};

/**
 * API MOBILE LEGACY (no rompe):
 * - Genera un UUID y lo guarda (según plataforma).
 * - Devuelve el UUID generado.
 */
export const createAndStoreDeviceId = async () => {
  const deviceId = uuidV4();
  log("Creando deviceId:", deviceId);

  await persistDeviceId(deviceId);
  return deviceId;
};

/**
 * API NUEVA RECOMENDADA (para unificación):
 * - Devuelve el deviceId existente o lo crea si no existe.
 * - Garantiza retornar un string (siempre).
 */
export const getOrCreateDeviceId = async () => {
  const existing = await getDeviceId();
  if (existing) return existing;

  return await createAndStoreDeviceId();
};

/**
 * API DESKTOP LEGACY (no rompe):
 * - Mantiene exactamente el contrato existente en desktop:
 *   { code, status, deviceId, source } o error con message.
 */
export const getStoredDeviceId = async () => {
  const found = await findStoredDeviceIdDetailed();

  if (found.deviceId) {
    return {
      code: 200,
      status: "success",
      deviceId: found.deviceId,
      source: found.source,
    };
  }

  return {
    code: 404,
    status: "error",
    message: "Device ID no encontrado en ningún almacenamiento",
    deviceId: null,
  };
};

/**
 * Utilidad opcional para pruebas:
 * - Elimina deviceId de NativeStorage (Cordova) y de stores web.
 * - Útil si quieres forzar regeneración.
 */
export const resetDeviceId = async () => {
  // Cordova: borrar NativeStorage si existe
  await nativeRemoveItem(DEVICE_KEY);

  // localforage
  try {
    await localforage.removeItem(DEVICE_KEY);
  } catch {
    // Ignorar
  }

  // backups
  safeCookieRemove(DEVICE_BACKUP_KEY);

  try {
    localStorage.removeItem(DEVICE_BACKUP_KEY);
  } catch {
    // No queremos romper producción si el storage está bloqueado (modo privado/políticas)
    log("localStorage bloqueado al eliminar deviceId_backup");
  }

  try {
    sessionStorage.removeItem(DEVICE_BACKUP_KEY);
  } catch {
    // No queremos romper producción si el storage está bloqueado (modo privado/políticas)
    log("sessionStorage bloqueado al eliminar deviceId_backup");
  }
};
