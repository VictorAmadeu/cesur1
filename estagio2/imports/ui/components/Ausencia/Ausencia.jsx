// Componente principal de gestión de ausencias del portal de empleado.
import React, { useEffect, useState } from 'react';
import { DatePickerSelect } from '../DatePickerSelect';
import { useDate } from '../../../provider/date';
import { toast } from 'react-toastify';
import { useNavigate } from 'react-router-dom';
import { Formulario } from './Formulario';
import { FormularioNew } from './FormularioNew';
import LicenseService from '/imports/service/licenseService';
import dayjs from 'dayjs';

/**
 * Componente Ausencia
 * - Muestra el listado de ausencias del año seleccionado.
 * - Permite crear una nueva ausencia y modificar una existente mediante modales.
 */
export const Ausencia = () => {
  const navigate = useNavigate();
  const { selectedYear } = useDate();

  // Indicador de carga del listado.
  const [loading, setLoading] = useState(false);
  // Datos devueltos por el backend (array de licencias).
  const [data, setData] = useState(null);
  // Control de visibilidad del modal de edición.
  const [showModal, setShowModal] = useState(false);
  // Control de visibilidad del modal de nueva ausencia.
  const [showModalNew, setShowModalNew] = useState(false);
  // Licencia seleccionada para modificar.
  const [selectedLicense, setSelectedLicense] = useState(null);

  // Mapa de estados de la licencia para pintar texto y color.
  const STATUS_MAP = {
    0: { text: 'En proceso', color: '#3a94cc' },
    1: { text: 'Aprobado', color: 'green' },
    2: { text: 'Rechazado', color: '#d83737' }
  };

  /**
   * Carga las ausencias del año seleccionado desde el backend.
   */
  const getInfo = async () => {
    try {
      setLoading(true);

      const r = await LicenseService.get({ year: selectedYear });

      // Si el backend indica sesión expirada, redirigimos a login.
      if (r.code === 401) {
        toast.error('Sesión finalizada', { position: 'top-center' });
        navigate('/login');
        return;
      }

      // Guardamos las licencias en estado.
      setData(r.data);
    } catch (error) {
      // Si falla la petición no modificamos el estado actual.
      // (Se podría loguear el error si fuera necesario.)
    } finally {
      setLoading(false);
    }
  };

  /**
   * Cada vez que cambia el año seleccionado, recargamos la información.
   */
  useEffect(() => {
    getInfo();
  }, [selectedYear]);

  /**
   * Abre el modal de edición con la licencia seleccionada.
   */
  const openModal = (license) => {
    setSelectedLicense(license);
    setShowModal(true);
  };

  /**
   * Cierra el modal de edición y limpia la licencia seleccionada.
   */
  const closeModal = () => {
    setSelectedLicense(null);
    setShowModal(false);
  };

  /**
   * Abre el modal para crear una nueva ausencia.
   */
  const openModalNew = () => {
    setShowModalNew(true);
  };

  /**
   * Cierra el modal de nueva ausencia.
   */
  const closeModalNew = () => {
    setShowModalNew(false);
  };

  return (
    <div className="px-2">
      <div className="w-full mx-auto max-w-4xl flex flex-col justify-center items-start">
        {/* Selector de año (usa el contexto global de fechas). */}
        <div className="desplegableFecha">
          <DatePickerSelect type={'year'} />
        </div>

        {loading ? (
          // Estado de carga mientras esperamos la respuesta del backend.
          <span className="w-full text-center">Cargando...</span>
        ) : (
          <>
            {/* Cabecera + botón para crear nueva ausencia */}
            <div className="flex justify-between w-full">
              <h1>Solicitadas</h1>
              <button
                className="bg-[#3a94cc] border-none text-white rounded-[7px] text-center text-sm py-1 my-auto px-2"
                onClick={openModalNew}
              >
                Nueva Ausencia
              </button>
            </div>

            {/* Caso: no hay datos devueltos para el año seleccionado */}
            {data && data.length === 0 ? (
              <section className="ausenciaBox">
                <div className="ausenciaHeader">
                  <div>
                    <span></span>
                  </div>
                </div>
                <div className="ausenciaBody">
                  <div className="ausenciaType"></div>
                  <div className="ausenciaDates ">
                    <p className="text-center">No hay ausencias registradas</p>
                  </div>
                  <div></div>
                  <div className="flex justify-end mt-2"></div>
                </div>
              </section>
            ) : (
              <>
                {/* Listado de ausencias */}
                {data?.map((license) => (
                  <section className="ausenciaBox" key={license.id}>
                    <div className="ausenciaHeader">
                      <div>
                        <span
                          style={{
                            color: STATUS_MAP[license.status]?.color,
                            fontWeight: 'bold'
                          }}
                        >
                          {STATUS_MAP[license.status]?.text || 'Desconocido'}
                        </span>
                      </div>
                    </div>

                    <div className="ausenciaBody">
                      {/* Tipo de ausencia (Vacaciones, Baja, etc.) */}
                      <div className="ausenciaType">{license.type}</div>

                      {/* Rango de fechas y horas de la ausencia */}
                      <div className="ausenciaDates">
                        <p>
                          <strong style={{ paddingRight: '4px' }}>
                            {dayjs(license.dateStart).format('DD/MM/YYYY')}
                            {license.timeStart ? ` ${license.timeStart} Hs` : ''}
                          </strong>
                          -{' '}
                          <strong>
                            {dayjs(license.dateEnd).format('DD/MM/YYYY')}
                            {license.timeEnd ? ` ${license.timeEnd} Hs` : ''}
                          </strong>
                        </p>
                      </div>

                      {/* Comentarios opcionales introducidos por el usuario */}
                      <div>{license.comments}</div>

                      {/* Indicador de número de documentos adjuntos, si existen */}
                      {license.documents?.length > 0 && (
                        <div className="text-sm text-gray-700 mt-1">
                          Adjuntos: {license.documents.length}
                        </div>
                      )}

                      {/* Botón para modificar la ausencia (no aparece si está rechazada) */}
                      <div className="flex justify-end mt-2">
                        {license.status === 2 ? null : (
                          <button
                            className="bg-[#3a94cc] border-none text-white rounded-[7px] text-center text-sm py-1 px-[7px]"
                            onClick={() => openModal(license)}
                          >
                            Modificar
                          </button>
                        )}
                      </div>
                    </div>
                  </section>
                ))}
              </>
            )}
          </>
        )}
      </div>

      {/* Modal de edición de ausencia */}
      {showModal && (
        <div className="fixed w-full inset-0 flex justify-center items-center bg-black/50 bg-opacity-50 z-50 h-screen overflow-auto">
          <Formulario license={selectedLicense} onClose={closeModal} onUpdate={getInfo} />
        </div>
      )}

      {/* Modal de nueva ausencia */}
      {showModalNew && (
        <div className="fixed w-full inset-0 flex justify-center items-center bg-black/50 bg-opacity-50 z-50 h-screen overflow-auto">
          <FormularioNew onClose={closeModalNew} onUpdate={getInfo} />
        </div>
      )}
    </div>
  );
};
