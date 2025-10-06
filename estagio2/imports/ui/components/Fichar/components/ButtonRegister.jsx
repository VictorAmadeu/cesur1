import React, { useState } from 'react';
import CheckinService from '/imports/service/checkinService';
import { toast } from 'react-toastify';
import { useCheckin } from '../../../../provider/checkIn';
import { getStoredDeviceId } from '/imports/utils/deviceUtils';

const ButtonRegister = ({ isEntry, selectedProject }) => {
  const [disabled, setDisabled] = useState(false);
  const [loading, setLoading] = useState(false);
  const { refreshTimes } = useCheckin();

  const registerTime = async () => {
    if (disabled || loading) return;

    setLoading(true);
    setDisabled(true);
    try {
      const getDevice = await getStoredDeviceId();
      const setTimeForBack = await CheckinService.register({
        project: selectedProject?.value ?? null,
        deviceId: getDevice?.deviceId ?? null
      });
      if (setTimeForBack.code !== 200) {
        toast.error(`${setTimeForBack.message}`, {
          position: 'top-center'
        });
      } else {
        toast.success(`${setTimeForBack.message}`, {
          position: 'top-center'
        });
      }
    } catch (error) {
      console.error('Error al registrar', error);
      toast.error(`${error.message}`, {
        position: 'top-center'
      });
    } finally {
      setLoading(false);
      setDisabled(false);
      refreshTimes();
    }
  };

  return (
    <div className="flex flex-col items-center gap-2 w-full justify-center text-white">
      <i className="fa-solid fa-stopwatch text-6xl" />
      <button
        disabled={disabled || loading}
        className={`py-2 px-5 border rounded-[10px] text-base shadow-md transition-all ease-linear ${
          disabled || loading
            ? 'bg-[#e28149a9] border-[#e28149a9] cursor-not-allowed'
            : 'bg-[#e28049] border-[#e28049] cursor-pointer'
        }`}
        onClick={registerTime}
      >
        {loading ? (
          <span className="text-white">Registrando...</span>
        ) : isEntry ? (
          <span className="text-white">Fichar entrada</span>
        ) : (
          <span className="text-white">Fichar salida</span>
        )}
      </button>
    </div>
  );
};

export default ButtonRegister;
