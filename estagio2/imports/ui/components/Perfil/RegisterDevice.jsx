import React, { useState } from 'react';
import localforage from 'localforage';
import { v4 as uuidv4 } from 'uuid';
import { toast } from 'react-toastify';
import { isMobile, isTablet, isDesktop } from 'react-device-detect'; // Asegúrate de tenerlo instalado
import DeviceService from '/imports/service/deviceService';
import Cookies from 'js-cookie';

localforage.config({
  name: "myApp",
  storeName: "deviceStore",
});

const RegisterDevice = ({ onClose, register, onUpdate }) => {
  const [deviceName, setDeviceName] = useState(register.name || '');

<<<<<<< HEAD
=======
  localforage.config({
    name: 'myApp',
    storeName: 'deviceStore'
  });

>>>>>>> jona
  const getDeviceType = () => {
    if (isMobile) return 'Mobile';
    if (isTablet) return 'Tablet';
    if (isDesktop) return 'Desktop';
    return 'Unknown';
  };

  const clearPreviousDevice = async () => {
    await localforage.removeItem("deviceId");
    localStorage.removeItem("deviceId_backup");
    sessionStorage.removeItem("deviceId_backup");
    Cookies.remove("deviceId_backup");
  };

  const handleRegisterDevice = async (e) => {
    e.preventDefault();
    try {
      const deviceType = getDeviceType();
<<<<<<< HEAD

      let deviceId = await localforage.getItem("deviceId");
=======
      let deviceId = await localforage.getItem('deviceId');
>>>>>>> jona

      if (!deviceId) {
        if (!deviceName.trim()) {
          toast.error('El nombre del dispositivo es obligatorio.', {
            position: 'top-center'
          });
          return;
        }

        await clearPreviousDevice();
        
        deviceId = uuidv4();

        // IndexedDB principal (localforage)
        await localforage.setItem('deviceId', deviceId);

        // Backup 1: localStorage
        localStorage.setItem('deviceId_backup', deviceId);

        // Backup 2: cookies
<<<<<<< HEAD
        Cookies.set("deviceId_backup", deviceId, {
          expires: 365 * 10,
          secure: true,
=======
        let expirationDate = new Date();
        expirationDate.setTime(expirationDate.getTime() + 60 * 60 * 24 * 365);
        Cookies.set('deviceId_backup', deviceId, {
          expires: expirationDate,
          secure: true
>>>>>>> jona
        });

        // Backup 3: sessionStorage
        sessionStorage.setItem('deviceId_backup', deviceId);
      }

      const response = await DeviceService.register({
        deviceId,
        deviceType,
        deviceName
      });

      if (response.code === 200) {
        setTimeout(() => {
          toast.success('Dispositivo registrado exitosamente', {
            position: 'top-center'
          });
        }, 1000);
        onUpdate();
        onClose();
      } else {
        toast.error(`Error al registrar el dispositivo: ${response.message}`, {
          position: 'top-center'
        });
      }
    } catch (error) {
      toast.error('Error al registrar el dispositivo', {
        position: 'top-center'
      });
    }
  };

  const closeBtn = () => {
    onClose();
  };

  return (
    <div className="bg-white w-full max-w-lg rounded-lg shadow-lg p-4 m-2 relative h-fit text-black z-60">
      <form onSubmit={handleRegisterDevice}>
        <button onClick={onClose} className="absolute top-2 right-2 text-gray-500">
          X
        </button>
        <h4 className="text-lg font-semibold mb-4">Registrar Dispositivo</h4>
        <div className="mb-4 w-full">
          <label className="block text-sm font-medium text-gray-700">Nombre del dispositivo</label>
          <input
            type="text"
            className="mt-1 block w-full rounded-md border px-2 py-1 border-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
            value={deviceName}
            onChange={(e) => setDeviceName(e.target.value)}
            disabled={register.code === 200}
            required
          />
        </div>
        <div className="w-full flex justify-between items-center gap-2">
          <button
            type="button"
            className="w-full bg-[#dc3545] text-white py-2 rounded-md"
            onClick={() => closeBtn()}
          >
            Cancelar
          </button>
          {register.code === 200 ? null : (
            <button type="submit" className="w-full bg-[#3a94cc] text-white py-2 rounded-md">
              Registrar
            </button>
          )}
        </div>
      </form>
    </div>
  );
};

export default RegisterDevice;
