// imports/ui/hooks/useDocuments.js
//
// Hook único para Documentos (web + móvil/Cordova).
// Objetivo: centralizar carga, selección de tipo, marcado como leído y descarga,
// evitando duplicidad entre DocumentosWeb.jsx y DocumentosMobile.jsx.
//
// ✅ Producción:
// - Usa callApiWithAuth (interceptor) y token cookie (como ya venías haciendo).
// - mark-read NO bloquea la descarga (se dispara en segundo plano).
// - Descarga por base64 (preferente) y fallback por URL si existe.
// - En Cordova: guarda en filesystem y abre con fileOpener2 (si está disponible).
//
// Dependencias:
// - js-cookie
// - file-saver
// - base64ToBlob (imports/utils/files.js)
// - useAuthInterceptor + callApi

import { useCallback, useMemo, useState } from 'react';
import Cookies from 'js-cookie';
import { Meteor } from 'meteor/meteor';
import { saveAs } from 'file-saver';

import { callApi } from '../../api/callApi';
import useAuthInterceptor from './useAuthInterceptor';
import { base64ToBlob } from '../../utils/files';

// -----------------------------------------------------------------------------
// Helpers (seguros para producción)
// -----------------------------------------------------------------------------
function normalizeBaseUrl(url) {
  if (!url) return '';
  return url.endsWith('/') ? url : `${url}/`;
}

function sanitizeFileName(name) {
  const safe = (name || 'documento.pdf')
    .replace(/[\\/:*?"<>|]/g, '_')
    .trim();

  return safe.length > 0 ? safe : 'documento.pdf';
}

function getMimeTypeFromName(fileName) {
  const ext = (String(fileName || '').split('.').pop() || '').toLowerCase();

  if (ext === 'pdf') return 'application/pdf';
  if (ext === 'jpg' || ext === 'jpeg') return 'image/jpeg';
  if (ext === 'png') return 'image/png';
  if (ext === 'gif') return 'image/gif';
  if (ext === 'csv') return 'text/csv';
  if (ext === 'xlsx') {
    return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
  }

  return 'application/octet-stream';
}

/**
 * En runtime Cordova cuelga de window.cordova, pero los typings pueden no existir.
 * Lo tratamos como `any` para evitar errores ts(2339) en VSCode en un .js.
 * @returns {any|null}
 */
function getCordova() {
  if (typeof window === 'undefined') return null;
  // eslint-disable-next-line no-restricted-globals
  const w = /** @type {any} */ (window);
  return w && w.cordova ? w.cordova : null;
}

function isCordovaEnv() {
  return Boolean(Meteor && Meteor.isCordova && getCordova());
}

function getResolveLocalFileSystemURL() {
  if (typeof window === 'undefined') return null;
  const w = /** @type {any} */ (window);
  return w && typeof w.resolveLocalFileSystemURL === 'function'
    ? w.resolveLocalFileSystemURL
    : null;
}

// -----------------------------------------------------------------------------
// Hook
// -----------------------------------------------------------------------------
export default function useDocuments() {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null); // string | null
  const [dataByType, setDataByType] = useState({}); // { [type: string]: Array<doc> }
  const [selectedType, setSelectedType] = useState(null);

  const callApiWithAuth = useAuthInterceptor(callApi);

  // Base del API sacada de Meteor.settings.public.baseUrl
  const apiBase = useMemo(() => {
    const baseUrl =
      (Meteor &&
        Meteor.settings &&
        Meteor.settings.public &&
        Meteor.settings.public.baseUrl) ||
      '';
    return normalizeBaseUrl(baseUrl);
  }, []);

  // Origen para construir URLs relativas (/path -> https://host/path)
  const apiOrigin = useMemo(() => {
    if (!apiBase) return '';

    try {
      const u = new URL(apiBase);
      return `${u.protocol}//${u.host}`;
    } catch (e) {
      // Caso muy raro: baseUrl inválida. No rompemos UX.
      console.warn('[useDocuments] baseUrl inválida en Meteor.settings.public.baseUrl:', e);
      return '';
    }
  }, [apiBase]);

  /**
   * Construye la URL completa de un archivo:
   * - Si docUrl ya trae esquema (http:, https:, blob:, file:, data:...), se devuelve tal cual.
   * - Si es relativa, se concatena con apiOrigin.
   */
  const buildFileUrl = useCallback(
    (docUrl) => {
      if (!docUrl) return null;

      const hasScheme = /^[a-zA-Z][a-zA-Z\d+\-.]*:/.test(docUrl);
      if (hasScheme) return docUrl;

      if (!apiOrigin) return null;
      const slash = docUrl.startsWith('/') ? '' : '/';
      return `${apiOrigin}${slash}${docUrl}`;
    },
    [apiOrigin]
  );

  /**
   * Carga documentos del endpoint "document".
   * El backend devuelve un objeto agrupado por tipo.
   */
  const load = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);

      const token = Cookies.get('tokenIntranEK');
      const response = await callApiWithAuth('document', undefined, token);

      const safeData =
        response && typeof response === 'object' && !Array.isArray(response)
          ? response
          : {};

      setDataByType(safeData);

      const keys = Object.keys(safeData);

      setSelectedType((prev) => {
        if (prev && safeData[prev]) return prev;
        return keys.length > 0 ? keys[0] : null;
      });
    } catch (e) {
      console.error('Error cargando documentos:', e);
      setDataByType({});
      setSelectedType(null);
      setError('No se pudieron cargar los documentos.');
    } finally {
      setLoading(false);
    }
  }, [callApiWithAuth]);

  /**
   * Cambia el tipo seleccionado (tab).
   */
  const setType = useCallback((type) => {
    setSelectedType(type || null);
  }, []);

  /**
   * Marca un documento como leído. Errores NO bloquean.
   * Si el backend confirma "200", refrescamos para actualizar viewedAt.
   */
  const markAsRead = useCallback(
    async (id) => {
      if (!id) return;

      try {
        const token = Cookies.get('tokenIntranEK');
        const res = await callApiWithAuth('document/mark-read', { id }, token);

        if (res && res.code === '200') {
          load();
        }
      } catch (e) {
        console.warn('No se pudo marcar como leído:', e);
      }
    },
    [callApiWithAuth, load]
  );

  /**
   * Descarga por URL en entorno web:
   * - Intento 1: fetch + blob + saveAs (fuerza descarga)
   * - Fallback: abrir la URL
   */
  const downloadFromUrlWeb = useCallback(async (fileUrl, fileName, token) => {
    const mimeType = getMimeTypeFromName(fileName);

    try {
      const headers = new Headers();
      if (token) headers.set('Authorization', `Bearer ${token}`);

      const resp = await fetch(fileUrl, {
        method: 'GET',
        headers,
        credentials: 'include',
      });

      if (!resp.ok) throw new Error(`HTTP ${resp.status}`);

      const blob = await resp.blob();
      if (!blob || blob.size === 0) throw new Error('Blob vacío');

      const finalBlob =
        blob.type && blob.type !== 'application/octet-stream'
          ? blob
          : new Blob([blob], { type: mimeType });

      saveAs(finalBlob, fileName);
      return true;
    } catch (e) {
      console.warn('Fallo fetch+blob; se intenta abrir URL:', e);
      return false;
    }
  }, []);

  /**
   * Abre una URL en el sistema:
   * - Cordova: InAppBrowser (_system) si existe
   * - Web: window.open con fallbacks
   */
  const openUrlSystem = useCallback((url) => {
    if (!url) return;

    try {
      const cordova = getCordova();
      const inAppBrowser = cordova && cordova.InAppBrowser;

      if (isCordovaEnv() && inAppBrowser && typeof inAppBrowser.open === 'function') {
        inAppBrowser.open(url, '_system');
        return;
      }

      const opened =
        window.open(url, '_system') ||
        window.open(url, '_blank') ||
        window.open(url);

      if (!opened) window.location.href = url;
    } catch (e) {
      console.error('No se pudo abrir la URL:', e);
      alert('No se pudo abrir el documento.');
    }
  }, []);

  /**
   * Guarda y abre un Blob en Cordova usando filesystem + fileOpener2 si existe.
   * Si no existe plugin, intenta InAppBrowser como fallback.
   */
  const saveBlobCordovaAndOpen = useCallback((blob, fileName, mimeType) => {
    try {
      if (!isCordovaEnv()) return false;

      const cordova = getCordova();
      const resolveLocalFileSystemURL = getResolveLocalFileSystemURL();
      const cordovaFile = cordova && cordova.file;

      if (!cordova || !cordovaFile || !resolveLocalFileSystemURL) return false;

      const folder = cordovaFile.externalDataDirectory || cordovaFile.dataDirectory;

      resolveLocalFileSystemURL(
        folder,
        (dir) => {
          dir.getFile(
            fileName,
            { create: true },
            (file) => {
              file.createWriter(
                (fileWriter) => {
                  fileWriter.onwriteend = () => {
                    const fullPath = folder + fileName;

                    const plugins = cordova.plugins;
                    const fileOpener2 = plugins && plugins.fileOpener2;
                    const inAppBrowser = cordova.InAppBrowser;

                    if (fileOpener2 && typeof fileOpener2.open === 'function') {
                      fileOpener2.open(fullPath, mimeType, {
                        error: (err) => console.error('No se pudo abrir el archivo:', err),
                      });
                    } else if (inAppBrowser && typeof inAppBrowser.open === 'function') {
                      inAppBrowser.open(fullPath, '_system');
                    } else {
                      window.open(fullPath, '_system');
                    }
                  };

                  fileWriter.write(blob);
                },
                (err) => console.error('Error al crear writer:', err)
              );
            },
            (err) => console.error('No se pudo crear el archivo:', err)
          );
        },
        (err) => console.error('No se pudo resolver la ruta del sistema de archivos:', err)
      );

      return true;
    } catch (e) {
      console.error('Fallo guardando en Cordova:', e);
      return false;
    }
  }, []);

  /**
   * Descarga/abre un documento:
   * - Marca como leído en segundo plano (no bloqueante).
   * - Si hay base64: base64→Blob→descarga (web) o guarda+abre (Cordova).
   * - Si no hay base64 pero hay url: web (fetch+descarga) -> fallback open; Cordova open directo.
   */
  const download = useCallback(
    async (doc) => {
      if (!doc) return;

      const fileName = sanitizeFileName(doc.name || 'documento.pdf');

      // NO bloquear la descarga por el marcado
      markAsRead(doc.id);

      // Caso 1: base64 preferente
      if (doc.base64) {
        try {
          const mimeType = getMimeTypeFromName(fileName);
          const blob = base64ToBlob(doc.base64, mimeType);

          if (isCordovaEnv()) {
            const started = saveBlobCordovaAndOpen(blob, fileName, mimeType);
            if (started) return;
          }

          saveAs(blob, fileName);
          return;
        } catch (e) {
          console.error(
            'Error descargando por base64; se intentará por URL si existe:',
            e
          );
        }
      }

      // Caso 2: URL fallback
      if (doc.url) {
        const fileUrl = buildFileUrl(doc.url);

        if (!fileUrl) {
          alert('No se pudo construir la URL del archivo.');
          return;
        }

        // Cordova: abrir directo (suele ser más estable que fetch en WebView)
        if (isCordovaEnv()) {
          openUrlSystem(fileUrl);
          return;
        }

        // Web: intentar descargar con fetch+blob
        const token = Cookies.get('tokenIntranEK');
        const ok = await downloadFromUrlWeb(fileUrl, fileName, token);

        if (!ok) openUrlSystem(fileUrl);
        return;
      }

      alert('Este documento no tiene contenido descargable (sin base64 ni URL).');
    },
    [buildFileUrl, downloadFromUrlWeb, markAsRead, openUrlSystem, saveBlobCordovaAndOpen]
  );

  const types = useMemo(() => Object.keys(dataByType || {}), [dataByType]);

  return {
    // state
    loading,
    error,
    dataByType,
    selectedType,
    types,

    // actions
    load,
    setType,
    markAsRead,
    download,
  };
}
