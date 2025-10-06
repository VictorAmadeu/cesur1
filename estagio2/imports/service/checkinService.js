import axiosClient from './axiosClient';

const CheckinService = {
  get: async (body) => {
    const response = await axiosClient.post('/license/getByYear', body);
    return response.data;
  },

  getByDate: async (body) => {
    try {
      const response = await axiosClient.post('timesRegister/getByDate', body);
      return response.data;
    } catch (error) {
      console.error('Error', error);
      return {
        message: error.response.data.message ?? 'Error',
        code: error.response.data.code ?? 404
      };
    }
  },

  getByDates: async (body) => {
    try {
      const response = await axiosClient.post('timesRegister/getByDates', body);
      return response.data;
    } catch (error) {
      console.error('Error', error);
      return {
        message: error.response.data.message ?? 'Error',
        code: error.response.data.code ?? 404
      };
    }
  },

  register: async (body) => {
    const response = await axiosClient.post('timesRegister/setTime', body);
    return response.data;
  },

  registerManual: async (body) => {
    try {
      const response = await axiosClient.post('timesRegister/setNewTime', body);
      return response.data;
    } catch (error) {
      console.log(error);
      throw error;
    }
  },

  edit: async (body) => {
    try {
      const response = await axiosClient.post('license/edit', body);
      return response.data;
    } catch (error) {
      console.log(error);
      throw error;
    }
  },

  getByJustificationStatus: async (body) => {
    try {
      const response = await axiosClient.post('timesRegister/by-justification-status', body);
      return response.data;
    } catch (error) {
      console.error('Error', error);
      return {
        message: error.response.data.message ?? 'Error',
        code: error.response.data.code ?? 404
      };
    }
  },

  sendJustification: async (body) => {
    try {
      const response = await axiosClient.post('timesRegister/justification', body);
      return response.data;
    } catch (error) {
      console.error('Error', error);
      return {
        message: error.response.data.message ?? 'Error',
        code: error.response.data.code ?? 404
      };
    }
  }
};

export default CheckinService;
