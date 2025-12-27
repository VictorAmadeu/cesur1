// /types/cordova-app.d.ts
// Declaración global mínima para que VSCode/TS entiendan 'App' en mobile-config.js

declare const App: {
  setPreference(name: string, value: string, platform?: string): void;
  accessRule(pattern: string, options?: any): void;
  appendToConfig(xml: string): void;
};
