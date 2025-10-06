// useProjects.ts
import { useEffect, useState } from "react";
import ProjectService from "/imports/service/projectService";
import useLoading from "./useLoading";

const useProjects = (enabled = true) => {
  const [projects, setProjects] = useState([]);
  const [selectedProject, setSelectedProject] = useState(null);
  const { loading, triggerLoading, completeLoading } = useLoading(false);

  const fetchProjects = async () => {
    triggerLoading();
    try {
      const response = await ProjectService.get();
      const options = response.data.map((p) => ({
        value: p.id,
        label: p.name,
      }));
      setProjects(options);
    } catch (error) {
      console.error("Error loading projects", error);
    } finally {
      completeLoading();
    }
  };

  useEffect(() => {
    if (enabled) fetchProjects();
  }, [enabled]);

  return {
    projects,
    selectedProject,
    setSelectedProject,
    loadingProjects: loading,
  };
};

export default useProjects;
