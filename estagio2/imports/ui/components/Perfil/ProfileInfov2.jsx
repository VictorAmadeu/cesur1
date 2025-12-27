// @ts-nocheck
import React, { useEffect, useState } from 'react';
import UserService from '/imports/service/userService';
import { toast } from 'react-toastify';
import { useNavigate } from 'react-router-dom';
import { Pencil, Save, Smartphone, Trash, X } from 'lucide-react';
import { usePermissions } from '/imports/context/permissionsContext';
import RegisterDevice from './RegisterDevice';
import { getDeviceId } from '/imports/utils/deviceUtils';
import DeviceService from '/imports/service/deviceService';

export const ProfileInfo = () => {
  const navigate = useNavigate();
  const { permissions } = usePermissions();
  const [edit, setEdit] = useState(false);
  const [loadingData, setLoadingData] = useState(true);
  const [data, setData] = useState([]);
  const [showModal, setShowModal] = useState(false);
  const [register, setRegister] = useState(false);

  console.log(permissions);

  const profileFields = [
    { key: 'name', label: 'Nombre' },
    { key: 'lastname1', label: 'Primer apellido' },
    { key: 'lastname2', label: 'Segundo apellido' },
    { key: 'email', label: 'E-mail' },
    { key: 'dni', label: 'DNI' },
    { key: 'phone', label: 'Teléfono' }
  ];

  useEffect(() => {
    getInfo();
  }, []);

  const getInfo = async () => {
    try {
      setLoadingData(true);
      const r = await UserService.profile();
      if (r.code === 200) {
        setData(r.data[0]);
        await checkDeviceRegistration();
      }
      console.log('USER:', r);
      console.log('DATA:', data);
    } catch (error) {
      console.log(error);
    } finally {
      setLoadingData(false);
    }
  };

  const checkDeviceRegistration = async () => {
    try {
      const getDevice = await getDeviceId();
      if (getDevice) {
        const response = await DeviceService.check(getDevice);
        console.log('RESPONSE:', response);
        setRegister(response);
      } else {
        setRegister({ status: 'error', code: 404, message: 'Dispositivo no encontrado', name: '' });
      }
    } catch (error) {
      console.error('Error al verificar el registro del dispositivo:', error);
      return false;
    }
  };

  const BotonArrepentimiento = async () => {
    try {
      const req = await UserService.deleteAccount();
      toast.success(`${req.message}`, { position: 'top-center' });
      setTimeout(() => {
        navigate('/login');
      }, 2000);
    } catch (error) {}
  };

  const closeModal = () => {
    setShowModal(false);
  };

  const handleChange = (key, value) => {
    setData((prev) => ({
      ...prev,
      [key]: value
    }));
  };

  const sendData = async () => {
    try {
      const req = await UserService.updateProfile(data);
      toast.success(`${req.message}`, { position: 'top-center' });
      setEdit(false);
      await getInfo();
    } catch (error) {
      console.error('Error actualizando perfil:', error);
      toast.error('Error actualizando perfil', { position: 'top-center' });
    }
  };

  if (loadingData) {
    return <span className="w-full flex justify-center text-center">Cargando...</span>;
  }

  return (
    <div className="w-screen p-4">
      <div className="border w-full rounded p-4">
        {profileFields.map(({ key, label }) => (
          <div key={key} className="mb-2">
            <strong>{label}</strong>
            <br />
            {edit ? (
              <input
                type="text"
                value={data[key] ?? ''}
                className="border px-2 py-1 rounded w-full"
              />
            ) : (
              <span className="text-red-500">{data[key] ?? 'Sin completar'}</span>
            )}
          </div>
        ))}
      </div>
      {edit ? (
        <div className="w-full flex justify-between items-center mt-4">
          <button
            className="border rounded bg-red-500 px-4 py-2 flex"
            onClick={() => setEdit(false)}
          >
            <X color="white" size={16} />
            <span className="text-xs pl-2 text-white">Cancelar</span>
          </button>
          <button className="border rounded bg-[#3a94cc] px-4 py-2 flex" onClick={sendData}>
            <Save color="white" size={16} />
            <span className="text-xs pl-2 text-white">Guardar</span>
          </button>
        </div>
      ) : (
        <div className="w-full flex justify-between items-center mt-4">
          <button
            className="border rounded bg-[#3a94cc] px-4 py-2 flex"
            onClick={() => setEdit(true)}
          >
            <Pencil color="white" size={16} />
            <span className="text-xs pl-2 text-white">Editar</span>
          </button>
          <button
            className="border rounded bg-red-500 px-4 py-2 flex"
            onClick={() => BotonArrepentimiento()}
          >
            <Trash color="white" size={16} />
            <span className="text-xs pl-2 text-white">Eliminar cuenta</span>
          </button>
        </div>
      )}
      {['ROLE_SUPERADMIN', 'ROLE_ADMIN'].includes(data.role) &&
        permissions.allowDeviceRegistration && (
          <button
            onClick={() => setShowModal(true)}
            className="border w-full rounded bg-[#3a94cc] mt-4 px-4 py-2 flex justify-center"
          >
            <Smartphone color="white" size={16} />
            <span className="text-xs pl-2 text-white">Dispositivo</span>
          </button>
        )}

      {showModal && (
        <div className="fixed w-full inset-0 flex justify-center items-center bg-black/50 bg-opacity-50 z-50 h-screen overflow-auto">
          <RegisterDevice onClose={closeModal} register={register} onUpdate={getInfo} />
        </div>
      )}
    </div>
  );
};
