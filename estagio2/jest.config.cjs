/**
 * Configuración de Jest para Intranek.
 *
 * - Usa jsdom para simular el DOM del navegador.
 * - Busca tests en /imports y /tests.
 * - Transforma JS/JSX con babel-jest.
 * - Ignora estilos importados (.css, .scss, etc.).
 * - Ejecuta jest.setup.js antes de cada suite de tests.
 */

 /** @type {import('jest').Config} */
module.exports = {
  // Entorno: simulación de navegador
  testEnvironment: 'jsdom',

  // Carpetas donde Jest buscará tests y código
  roots: ['<rootDir>/imports', '<rootDir>/tests'],

  // Patrones de nombres de fichero de test
  testMatch: [
    '**/__tests__/**/*.(js|jsx)',
    '**/*.(test|spec).(js|jsx)',
  ],

  // Cómo transformar archivos JS/JSX antes de ejecutarlos
  transform: {
    '^.+\\.(js|jsx)$': 'babel-jest',
  },

  // Alias / mocks de módulos
  moduleNameMapper: {
    // Permitir imports absolutos tipo "/imports/..."
    '^/imports/(.*)$': '<rootDir>/imports/$1',

    // Ignorar estilos: se reemplazan por un objeto vacío
    '\\.(css|less|scss|sass)$': 'identity-obj-proxy',
  },

  // Fichero que se ejecuta antes de cada test (mocks globales, matchers, etc.)
  setupFilesAfterEnv: ['<rootDir>/jest.setup.js'],

  // Qué ficheros entran en la cobertura
  collectCoverageFrom: [
    'imports/**/*.{js,jsx}',
    '!imports/**/__tests__/**',
    '!imports/**/index.js',
    '!imports/ui/routes/**',
    '!imports/ui/pages/**',
  ],

  // Umbrales mínimos globales (por ahora modestos)
  coverageThreshold: {
    global: {
      branches: 30,
      functions: 30,
      lines: 30,
      statements: 30,
    },
  },

  // Tiempo máximo de cada test (ms)
  testTimeout: 10000,

  // Mostrar detalle de cada test
  verbose: true,
};
