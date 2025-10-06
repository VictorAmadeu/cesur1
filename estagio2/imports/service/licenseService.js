import axiosClient from "./axiosClient";

const LicenseService = {
  get: async (body) => {
    try {
      const response = await axiosClient.post("/license/getByYear", body);
      return response.data;
    } catch (error) {
      throw error;
    }
  },

  getOne: async (body) => {
    try {
      const response = await axiosClient.post("/license/getOne", body);
      return response.data;
    } catch (error) {
      throw error;
    }
  },

  register: async (body) => {
    try {
      const response = await axiosClient.post("/license/create", body);
      return response.data;
    } catch (error) {
      console.log(error);
      throw error;
    }
  },

  edit: async (body) => {
    try {
      const response = await axiosClient.post("/license/edit", body);
      return response.data;
    } catch (error) {
      console.log(error);
      throw error;
    }
  },

  upload: async (body) => {
    try {
      const response = await axiosClient.post("/license/upload-file", body);
      return response.data;
    } catch (error) {
      console.log(error);
      throw error;
    }
  },
};

export default LicenseService;
