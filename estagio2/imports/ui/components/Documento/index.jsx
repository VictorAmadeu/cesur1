// imports/ui/components/Documento/index.jsx
//
// Punto de entrada único para el módulo Documento.
// ✅ Compatibilidad:
// - Mantengo `DocumentosIndex` (como venías exportando).
// - Además exporto default para no romper imports alternativos.
//
// Nota: la responsividad (web/móvil) ya está dentro de Documentos.jsx.

import React from 'react';
import Documentos from './Documentos';

export const DocumentosIndex = () => {
  return (
    <div style={{ padding: '10px' }}>
      <Documentos />
    </div>
  );
};

export default Documentos;
