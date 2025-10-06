import axiosClient from "./axiosClient";

const CompanyService = {
  get: async () => {
    try {
      const response = await axiosClient.post("/companies/getAll");
      return response.data;
    } catch (error) {
      throw error;
    }
  },

  getLogo: async () => {
    try {
      const response = await axiosClient.post("/companies/getLogos");
      return response.data;
    } catch (error) {
      return { message: error.response.data.message ?? "Error", code: error.response.data.code ?? 404 };
    }
  },

  setLogo: async (logo) => {
    try {
      const response = await axiosClient.post("/companies/setLogo", logo);
      return response.data;
    } catch (error) {
      throw error;
    }
  },
};

export default CompanyService;
