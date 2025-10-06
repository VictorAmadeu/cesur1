import axiosClient from './axiosClient';

const DeviceService = {
  check: async (body) => {
    const response = await axiosClient.post('/device/check-registration', { deviceId: body });
    return response.data;
  },

  register: async (body) => {
    try {
      const response = await axiosClient.post('/device/register', body);
      return response.data;
    } catch (error) {
      console.log(error);
      throw error;
    }
  }
};

export default DeviceService;
