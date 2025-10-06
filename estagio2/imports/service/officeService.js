import axiosClient from "./axiosClient";

const OfficeService = {
  calculateDistance: async (body) => {
    try {
      const response = await axiosClient.post("/office/calculate-distance", body);
      return response.data;
    } catch (error) {
      throw error;
    }
  },

};

export default OfficeService;
