import { useState, useCallback } from "react";

const useLoading = (initialState = true) => {
  const [loading, setLoading] = useState(initialState);

  const triggerLoading = useCallback((value = true) => {
    setLoading(value);
  }, []);

  const completeLoading = useCallback(() => {
    setLoading(false);
  }, []);

  return {
    loading,
    setLoading,
    triggerLoading,
    completeLoading,
  };
};

export default useLoading;
