import { useNavigate } from "react-router-dom";
import { useCallback } from "react";

const useAuthInterceptor = (apiFunction) => {
  const navigate = useNavigate();

  const intercept = useCallback(
    async (...args) => {
      try {
        const response = await apiFunction(...args);
        if (response.status === 401 || response.code === 401) {
          navigate("/login");
          return null;
        }

        return response;
      } catch (error) {
        console.error("Error in API call:", error);
        throw error;
      }
    },
    [apiFunction, navigate]
  );

  return intercept;
};

export default useAuthInterceptor;
