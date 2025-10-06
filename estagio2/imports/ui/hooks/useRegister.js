import React from "react";
import { useNavigate } from "react-router-dom";
import { usePermissions } from "../../context/permissionsContext";
import AuthService from "../../service/authService";
import { getStoredDeviceId } from "../../utils/deviceUtils";
import CheckinService from "../../service/checkinService";
import { useDate } from "../../provider/date";
import dayjs from "../../utils/dayjsConfig";

export const useRegister = ({ selectedProject, date }) => {
  const navigate = useNavigate();
  const { permissions } = usePermissions();
  const { selectedDate } = useDate();

  const registerTime = async ({ isToday = null } = {}) => {
    try {
      const checkSession = await AuthService.isAuthenticated();
      if (checkSession.code !== "200") {
        navigate("/login");
        return;
      }

      let deviceId = null;

      if (permissions.allowDeviceRegistration) {
        const checkDevice = await getStoredDeviceId();
        if (!checkDevice.deviceId) {
          return [{ message: "No se encontró el dispositivo.", code: 400 }];
        }
        deviceId = checkDevice.deviceId;
      }

      const response = [];

      if (isToday) {
        response = await CheckinService.register({
          project: selectedProject ?? null,
          deviceId,
          date,
        });
      } else {
        response = await CheckinService.registerManual({
          hourStart: `${dayjs(selectedDate).format(
            "YYYY-MM-DD"
          )}T${startTime}:00`,
          hourEnd: `${dayjs(selectedDate).format("YYYY-MM-DD")}T${endTime}:00`,
          project: selectedProject ?? null,
          deviceId,
        });
      }

      if (response.code === 400) {
        return [{ message: `${response.message}`, code: 400 }];
      } else {
        return [{ message: `${response.message}`, code: 200 }];
      }
    } catch (error) {
      return [{ message: `${error.message}`, code: 400 }];
    }
  };

  return { registerTime };
};
