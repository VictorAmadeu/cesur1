// estagio2/imports/ui/utils/files.js
// Función utilitaria para convertir cadenas base64 en Blobs.
// Esto es necesario para descargar archivos en navegadores móviles,
// ya que los data URLs largos no se manejan bien.
// Recibe el contenido en base64 y el tipo MIME del archivo.
export function base64ToBlob(base64, contentType = 'application/octet-stream') {
  // Si el string incluye una coma, quitamos el prefijo "data:…,".  
  const clean = base64.includes(',') ? base64.split(',')[1] : base64;
  const byteChars = atob(clean);
  const byteNumbers = new Array(byteChars.length);
  for (let i = 0; i < byteChars.length; i++) {
    byteNumbers[i] = byteChars.charCodeAt(i);
  }
  const byteArray = new Uint8Array(byteNumbers);
  return new Blob([byteArray], { type: contentType });
}
