import { createImage } from "../../utils";


export const getCroppedImg = async (imageSrc, pixelCrop, fileType = "image/jpeg") => {
  const image = await createImage(imageSrc);
  const canvas = document.createElement("canvas");
  const ctx = canvas.getContext("2d");

  const maxSize = Math.min(image.width, image.height);
  const radius = maxSize / 2;

  canvas.width = maxSize;
  canvas.height = maxSize;

  ctx.beginPath();
  ctx.arc(radius, radius, radius, 0, 2 * Math.PI);
  ctx.closePath();
  ctx.clip();

  ctx.drawImage(
    image,
    pixelCrop.x,
    pixelCrop.y,
    pixelCrop.width,
    pixelCrop.height,
    0,
    0,
    maxSize,
    maxSize
  );

  return new Promise((resolve, reject) => {
    try {
      const base64Image = canvas.toDataURL(fileType); // Convertir a Base64 con el formato correcto
      resolve({ base64: base64Image.split(",")[1], ext: fileType.split("/")[1] }); // Devolver sin prefijo
    } catch (error) {
      reject(error);
    }
  });
};

