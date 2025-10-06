import axiosClient from './axiosClient';

const WorkSheduleService = {
  getWorkShedule: async (body) => {
    const response = await axiosClient.post('/work_shedule/range', body);
    return response.data;
  },

  extraByRage: async (body) => {
    const response = await axiosClient.post('/work_shedule/extra-range', body);
    return response.data;
  },

  checkDay: async (body) => {
    const response = await axiosClient.post('/work_shedule/check', body);
    return response.data;
  }
};

export default WorkSheduleService;
