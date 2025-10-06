import React from 'react';
import Select from 'react-select';

const ProjectSelector = ({
  permissions,
  projects,
  selectedProject,
  setSelectedProject,
  loading,
  timesForDay
}) => {
  if (!permissions?.allowProjects) return null;

  if (loading) return <p>Cargando proyectos...</p>;

  // Mostrar mensaje cuando hay fichaje activo (status === 0)
  if (timesForDay && timesForDay?.status === 0) {
    return (
      <p>
        {timesForDay.project
          ? `Trabajando en ${timesForDay.project}`
          : 'No estás trabajando en ningún proyecto'}
      </p>
    );
  }

  // Si no hay fichaje activo, mostrar selector
  return (
    <div className="my-2 min-w-56">
      <Select
        options={projects}
        value={selectedProject}
        onChange={setSelectedProject}
        placeholder="Selecciona un proyecto"
        className="mt-1"
        classNamePrefix="react-select"
      />
    </div>
  );
};

export default ProjectSelector;
