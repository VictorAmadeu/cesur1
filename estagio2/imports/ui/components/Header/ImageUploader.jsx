// @ts-nocheck
import React, { useState, useCallback } from 'react';
import Cookies from 'js-cookie';
import { callApi, sendFormData } from '../../../api/callApi';
import { toast } from 'react-toastify';
import 'react-toastify/dist/ReactToastify.css';
import Cropper from 'react-easy-crop';
import { getCroppedImg } from '../cropImage';
import useAuthInterceptor from '../../hooks/useAuthInterceptor';
import CompanyService from '/imports/service/companyService';

const ImageUploader = ({ logo, onImageChange, closeModal }) => {
  const [image, setImage] = useState(logo);
  const [file, setFile] = useState(null);
  const [crop, setCrop] = useState({ x: 0, y: 0 });
  const [zoom, setZoom] = useState(1);
  const [croppedAreaPixels, setCroppedAreaPixels] = useState(null);
  const [errorMessage, setErrorMessage] = useState(null);
  const [croppingEnabled, setCroppingEnabled] = useState(false);
  const [loading, setLoading] = useState(false);

  const callApiWithAuth = useAuthInterceptor(callApi);

  const handleImageChange = (e) => {
    const selectedFile = e.target.files[0];
    const reader = new FileReader();

    reader.onloadend = () => {
      setImage(reader.result);
      setFile(selectedFile);
      onImageChange(selectedFile);
      setCroppingEnabled(true);
    };

    if (selectedFile) {
      reader.readAsDataURL(selectedFile);
      setErrorMessage(null);
    } else {
      setErrorMessage('No se ha seleccionado ningún archivo.');
    }
  };

  const onCropComplete = useCallback((croppedArea, croppedAreaPixels) => {
    setCroppedAreaPixels(croppedAreaPixels);
  }, []);

  const saveLogo = async () => {
    try {
      if (!file) {
        setErrorMessage('No se ha seleccionado ninguna imagen.');
        return;
      }

      let base64String, extension;

      setLoading(true);
      if (croppingEnabled) {
        const result = await getCroppedImg(image, croppedAreaPixels, file.type);
        base64String = result.base64;
        extension = result.ext;
      } else {
        base64String = image.split(',')[1]; // Extraer solo la parte base64
        extension = file.type.split('/')[1] || file.name.split('.').pop(); // Obtener extensión segura
      }

      const token = Cookies.get('tokenIntranEK');

      const response = await CompanyService.setLogo({
        logo64: base64String,
        ext: extension
      });

      toast.success(`${response.message}`, { position: 'top-center' });
      if (response.code === 200) {
        window.location.reload();
        closeModal();
      }
      setLoading(false);
    } catch (error) {
      console.error('Error al guardar el logo:', error);
      setErrorMessage('Error al guardar el logo.');
    }
  };

  const handleCloseModal = () => {
    setImage(logo);
    closeModal();
  };

  const modalStyles = {
    modalContainer: {
      display: 'block',
      position: 'fixed',
      zIndex: 1,
      left: 0,
      top: 0,
      width: '100%',
      height: '100%',
      overflow: 'auto',
      backgroundColor: 'rgba(0, 0, 0, 0.4)'
    },
    modalContent: {
      backgroundColor: '#fefefe',
      margin: '0 auto', // Centrar el modal horizontalmente
      padding: 20,
      border: '1px solid #888',
      width: '90%', // Ancho del modal
      maxWidth: 400, // Ancho máximo del modal
      position: 'relative',
      borderRadius: 10,
      paddingBottom: 10, // Espacio para los botones en el footer
      marginTop: 20
    },
    close: {
      color: '#aaa',
      position: 'absolute',
      top: 10,
      right: 10,
      fontSize: 28,
      fontWeight: 'bold',
      cursor: 'pointer'
    },
    imageUploader: {
      display: 'flex',
      flexDirection: 'column',
      alignItems: 'center'
    },
    imageUploaderDiv: {
      display: 'flex',
      fontSize: '15px',
      with: '100%',
      gap: '20px',
      alignItems: 'center',
      marginTop: 20 // Espacio entre la imagen y los botones
    },
    imageContainer: {
      width: '100%',
      textAlign: 'center'
    },
    image: {
      maxWidth: '100%',
      maxHeight: '60vh', // Altura máxima del modal
      objectFit: 'contain', // Ajustar la imagen dentro del contenedor
      marginTop: 20,
      borderRadius: '50%'
    },
    inputFile: {
      display: 'none'
    },
    uploadButton: {
      backgroundColor: '#4caf50',
      height: '50px',
      color: 'white',
      padding: '5px 10px',
      border: 'none',
      borderRadius: 4,
      cursor: 'pointer',
      width: '100%'
    },
    errorMessage: {
      color: 'red',
      textAlign: 'center',
      margin: '10px 0',
      with: '100%'
    }
  };

  return (
    <div className="modal-container" style={modalStyles.modalContainer}>
      <div className="modal-content" style={modalStyles.modalContent}>
        <span className="close" style={modalStyles.close} onClick={handleCloseModal}>
          &times;
        </span>
        <div className="image-uploader" style={modalStyles.imageUploader}>
          <div style={{ position: 'relative', width: '100%', height: 370 }}>
            {/* Renderizar la imagen directamente si el recorte está deshabilitado */}
            {!croppingEnabled && <img src={`${image}`} alt="Uploaded" style={modalStyles.image} />}
            {/* Renderizar el componente Cropper solo si el recorte está habilitado */}
            {croppingEnabled && (
              <Cropper
                image={image}
                crop={crop}
                zoom={zoom}
                aspect={1} // Aspecto 1:1
                onCropChange={setCrop}
                onZoomChange={setZoom}
                onCropComplete={onCropComplete}
                cropShape="round"
              />
            )}
          </div>
          <div className="image-uploader-div" style={modalStyles.imageUploaderDiv}>
            <input
              type="file"
              accept="image/*"
              onChange={handleImageChange}
              style={modalStyles.inputFile}
            />
            <button
              onClick={() => document.querySelector('input[type="file"]').click()}
              className="w-full flex flex-col justify-center items-center bg-[#3a94cc] text-white py-2 px-4 rounded-md text-center break-words"
            >
              Cargar <br /> Imagen
            </button>

            <button
              onClick={saveLogo}
              className="w-full flex flex-col justify-center items-center bg-[#3a94cc] text-white py-2 px-4 rounded-md text-center break-words"
            >
              Guardar <br /> Imagen
            </button>
          </div>
          {errorMessage && (
            <span className="text-red-500 text-center my-2 w-full font-semibold">
              {errorMessage}
            </span>
          )}
          {loading && (
            <span className="text-green-500 text-center my-2 w-full font-semibold">
              Guardando...
            </span>
          )}
        </div>
      </div>
    </div>
  );
};

export default ImageUploader;
