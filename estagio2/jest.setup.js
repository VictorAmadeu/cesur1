// @ts-nocheck

/**
 * Fichero de configuración que se ejecuta antes de cada test.
 *
 * - Activa los matchers extra de @testing-library/jest-dom.
 * - Define mocks globales para js-cookie.
 * - Define un mock básico para axiosClient (evita llamadas HTTP reales).
 * - Silencia los logs de consola durante los tests (para evitar ruido).
 */

// Matchers personalizados: toBeInTheDocument, toHaveTextContent, etc.
import '@testing-library/jest-dom';

// Mock de js-cookie (lectura/escritura de cookies)
jest.mock('js-cookie', () => ({
  set: jest.fn(),
  get: jest.fn(),
  remove: jest.fn(),
}));

// Mock de axiosClient: todas las llamadas HTTP serán funciones “espía”
jest.mock('/imports/service/axiosClient', () => ({
  __esModule: true,
  default: {
    get: jest.fn(),
    post: jest.fn(),
    put: jest.fn(),
    delete: jest.fn(),
  },
}));

// Opcional: silenciar console.log / console.error en los tests
// (así los tests solo fallan por aserciones, no por ruido de logs)
const originalConsole = global.console;

beforeAll(() => {
  global.console = {
    ...originalConsole,
    log: jest.fn(),
    warn: jest.fn(),
    error: jest.fn(),
  };
});

afterAll(() => {
  global.console = originalConsole;
});
