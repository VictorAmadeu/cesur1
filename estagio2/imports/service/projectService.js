import axiosClient from "./axiosClient";

const ProjectService = {
  get: async () => {
    try {
      const response = await axiosClient.post("/projects/get");
      return response.data;
    } catch (error) {
      console.log(error)
      throw error;
    }
  },

};

export default ProjectService;
