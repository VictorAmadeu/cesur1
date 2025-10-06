// @ts-nocheck
import React, { useState, useEffect } from 'react';
import { callApi } from '../../../api/callApi';
import Cookies from 'js-cookie';
import RegisterDevice from './RegisterDevice';
import { useNavigate } from 'react-router-dom';
import { toast } from 'react-toastify';
import localforage from 'localforage';
import { v4 as uuidv4 } from 'uuid';
import { isMobile, isTablet, isDesktop } from 'react-device-detect';
import 'react-toastify/dist/ReactToastify.css';
import useAuthInterceptor from '../../hooks/useAuthInterceptor';
import DeviceService from '/imports/service/deviceService';
import { getStoredDeviceId } from '/imports/utils/deviceUtils';
import { Pencil, Save, Smartphone, Trash, X } from 'lucide-react';

export const ProfileInfo = () => {
  const navigate = useNavigate();
  const [loadingData, setLoadingData] = useState(true);
  const [data, setData] = useState({});
  const [editedFields, setEditedFields] = useState({});
  const [isEditing, setIsEditing] = useState(false);
  const [showModal, setShowModal] = useState(false);
  const [deviceName, setDeviceName] = useState('');
  const [canRegisterDevice, setCanRegisterDevice] = useState(false);
  const [register, setRegister] = useState([]);

  const callApiWithAuth = useAuthInterceptor(callApi);

  useEffect(() => {
    getInfo();
  }, []);

  const checkRegisterStatus = async () => {
    try {
      const result = await isRegisterDeviceEnabled();
      setCanRegisterDevice(result);
      if (result) {
        checkDeviceRegistration();
      }
    } catch (error) {
      console.error('Error checking if device registration is enabled:', error);
    } finally {
      setLoadingData(false);
    }
  };

  const checkDeviceRegistration = async () => {
    try {
      const getDevice = await getStoredDeviceId();
      if (getDevice.code === 200) {
        const response = await DeviceService.check(getDevice.deviceId);
        setRegister(response);
      }
    } catch (error) {
      console.error('Error al verificar el registro del dispositivo:', error);
      return false;
    }
  };

  const closeModal = () => {
    setShowModal(false);
  };

  localforage.config({
    name: 'myApp',
    storeName: 'deviceStore'
  });

  const getDeviceType = () => {
    if (isMobile) return 'Mobile';
    if (isTablet) return 'Tablet';
    if (isDesktop) return 'Desktop';
    return 'Unknown';
  };

  const isRegisterDeviceEnabled = async () => {
    try {
      const token = Cookies.get('tokenIntranEK');
      const response = await callApiWithAuth('device/can-register', undefined, token);
      return response.allowDeviceRegistration;
    } catch (error) {
      throw new Error('Error al obtener la compañía');
    }
  };

  const getInfo = async () => {
    try {
      setLoadingData(true);
      const token = Cookies.get('tokenIntranEK');
      const r = await callApiWithAuth('user/profile', undefined, token);
      if (r.code === 401) {
        toast.error(`Sesión finalizada`, { position: 'top-center' });
        navigate('/login');
      }
      setData(r.data[0]);
      checkRegisterStatus();
    } catch (error) {
      console.log(error);
    }
  };

  const BotonArrepentimiento = async () => {
    try {
      const token = Cookies.get('tokenIntranEK');
      const req = await callApiWithAuth('user/disable', undefined, token);
      toast.success(`${req.message}`, { position: 'top-center' });
      setTimeout(() => {
        navigate('/login');
      }, 2000);
    } catch (error) {}
  };

  const handleEditField = (field, value) => {
    setEditedFields({ ...editedFields, [field]: value });
  };

  const sendData = async () => {
    try {
      const token = Cookies.get('tokenIntranEK');
      const req = await callApiWithAuth('user/edit', editedFields, token);
      toast.success(`${req.message}`, { position: 'top-center' });
      setTimeout(() => {
        setIsEditing(false);
        getInfo();
      }, 1000);
    } catch (error) {}
  };

  if (!data) return <div>No hay información</div>;

  return (
    <div
      style={{
        width: '100%',
        maxWidth: '800px',
        height: '100%',
        display: 'flex',
        justifyContent: 'center',
        margin: '20px auto 40px'
      }}
    >
      {loadingData ? (
        <div className="w-full flex justify-center">
          <p>Cargando...</p>
        </div>
      ) : (
        <div
          style={{
            width: '100%',
            maxWidth: '500px',
            padding: '0 20px',
            height: '100%'
          }}
        >
          <div className="perfilBox">
            <div className="perfilCampo">
              <p>
                <strong>Nombre</strong>
                <br />
                {isEditing ? (
                  <input
                    type="text"
                    value={editedFields.name || data.name || ''}
                    onChange={(e) => handleEditField('name', e.target.value)}
                  />
                ) : (
                  data.name
                )}
              </p>
            </div>
            <div className="perfilCampo">
              <p>
                <strong>Primer apellido</strong>
                <br />
                {isEditing ? (
                  <input
                    type="text"
                    value={editedFields.lastname1 || data.lastname1 || ''}
                    onChange={(e) => handleEditField('lastname1', e.target.value)}
                  />
                ) : (
                  data.lastname1
                )}
              </p>
            </div>
            <div className="perfilCampo">
              <p>
                <strong>Segundo apellido</strong>
                <br />
                {isEditing ? (
                  <input
                    type="text"
                    value={editedFields.lastname2 || data.lastname2 || ''}
                    onChange={(e) => handleEditField('lastname2', e.target.value)}
                  />
                ) : (
                  data.lastname2
                )}
              </p>
            </div>
            <div className="perfilCampo">
              <p>
                <strong>E-mail</strong>
                <br />
                {isEditing ? (
                  <input
                    type="text"
                    value={editedFields.email || data.email || ''}
                    onChange={(e) => handleEditField('email', e.target.value)}
                  />
                ) : (
                  data.email
                )}
              </p>
            </div>
            <div className="perfilCampo">
              <p>
                <strong>DNI</strong>
                <br />
                {isEditing ? (
                  <input
                    type="text"
                    value={editedFields.dni || data.dni || ''}
                    onChange={(e) => handleEditField('dni', e.target.value)}
                  />
                ) : (
                  data.dni
                )}
              </p>
            </div>
            <div className="perfilCampo">
              <p>
                <strong>Teléfono</strong>
                <br />
                {isEditing ? (
                  <input
                    type="text"
                    value={editedFields.phone || data.phone || ''}
                    onChange={(e) => handleEditField('phone', e.target.value)}
                  />
                ) : (
                  data.phone
                )}
              </p>
            </div>
          </div>
          <div className="perfilDelete h-fit">
            {!isEditing &&
              canRegisterDevice &&
              ['ROLE_SUPER_ADMIN', 'ROLE_ADMIN'].includes(data.role) &&
              (register?.code === 200 ? (
                <button
                  className="w-full flex justify-center items-center bg-[#3a94cc] text-white h-fit rounded-md"
                  onClick={() => setShowModal(true)}
                >
                  <Smartphone />
                  <span className="ml-2">Dispositivo Registrado</span>
                </button>
              ) : (
                <button
                  className="w-full flex justify-center items-center bg-[#3a94cc] text-white h-fit rounded-md"
                  onClick={() => setShowModal(true)}
                >
                  <Smartphone />
                  <span className="ml-2">Registrar Dispositivo</span>
                </button>
              ))}
            {isEditing ? (
              <div className="w-full flex justify-between items-center gap-2 ">
                <button
                  className="w-full flex justify-center items-center bg-[#dc3545] text-white h-fit rounded-md"
                  onClick={() => setIsEditing(false)}
                >
                  <X /> <span>Cancelar</span>
                </button>
                <button
                  className="w-full flex justify-center items-center bg-[#3a94cc] text-white h-fit rounded-md"
                  onClick={sendData}
                >
                  <span>
                    <Save />
                  </span>{' '}
                  <span>Guardar</span>
                </button>
              </div>
            ) : (
              <button
                className="w-full flex-grow flex justify-center items-center bg-[#3a94cc] text-white rounded-md"
                onClick={() => setIsEditing(true)}
              >
                <span>
                  <Pencil className="text-white" />
                </span>{' '}
                <span>Editar</span>
              </button>
            )}
            {isEditing ? null : (
              <button
                className="w-full flex justify-center items-center bg-[#dc3545] text-white h-fit rounded-md"
                onClick={() => BotonArrepentimiento()}
              >
                <span>
                  <Trash className="text-red-600" />
                </span>{' '}
                <span>Eliminar cuenta</span>
              </button>
            )}
          </div>
          {/* Modal para registrar el dispositivo */}
          {showModal && (
            <div className="fixed w-full inset-0 flex justify-center items-center bg-black/50 bg-opacity-50 z-50 h-screen overflow-auto">
              <RegisterDevice onClose={closeModal} register={register} onUpdate={getInfo} />
            </div>
          )}
        </div>
      )}
    </div>
  );
};
