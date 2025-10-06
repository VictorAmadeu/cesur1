import axiosClient from "./axiosClient";

const UserService = {
  profile: async () => {
    try {
      const response = await axiosClient.post("/user/profile");
      return response.data;
    } catch (error) {
      throw error;
    }
  },

  updateProfile: async (data) => {
    try {
        const response = await axiosClient.post("/user/edit", data);
        return response.data;
    } catch (error) {}
  },

  deleteAccount: async () => {
    try {
      const response = await axiosClient.post("/user/disable");
      return response.data;
    } catch (error) {
      throw error;
    }
  },
};

export default UserService;
