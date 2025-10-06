import React, { useEffect, useState } from 'react';
import { DatePickerSelect } from '../DatePickerSelect';
import { useDate } from '../../../provider/date';
import { toast } from 'react-toastify';
import 'react-toastify/dist/ReactToastify.css';
import 'react-toastify/dist/ReactToastify.css';
import { useNavigate } from 'react-router-dom';
import { Formulario } from './Formulario';
import { FormularioNew } from './FormularioNew';
import LicenseService from '/imports/service/licenseService';
import dayjs from 'dayjs';

export const Ausencia = () => {
  const navigate = useNavigate();
  const { selectedYear } = useDate();
  const [loading, setLoading] = useState(false);
  const [data, setData] = useState(null);
  const [showModal, setShowModal] = useState(false);
  const [showModalNew, setShowModalNew] = useState(false);
  const [selectedLicense, setSelectedLicense] = useState(null);

  const STATUS_MAP = {
    0: { text: 'En proceso', color: '#3a94cc' },
    1: { text: 'Aprobado', color: 'green' },
    2: { text: 'Rechazado', color: '#d83737' }
  };

  const getInfo = async () => {
    try {
      setLoading(true);
      const r = await LicenseService.get({
        year: selectedYear
      });

      if (r.code === 401) {
        toast.error(`Sesión finalizada`, { position: 'top-center' });
        navigate('/login');
      }
      setData(r.data);
    } catch (error) {
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    getInfo();
  }, [selectedYear]);

  const openModal = (license) => {
    setSelectedLicense(license);
    setShowModal(true);
  };

  const closeModal = () => {
    setSelectedLicense(null);
    setShowModal(false);
  };

  const openModalNew = () => {
    setShowModalNew(true);
  };

  const closeModalNew = () => {
    setShowModalNew(false);
  };

  return (
    <div className="px-2">
      <div className="w-full mx-auto max-w-4xl flex flex-col justify-center items-start">
        <div className="desplegableFecha">
          <DatePickerSelect type={'year'} />
        </div>
        {loading ? (
          <span className="w-full text-center">Cargando...</span>
        ) : (
          <>
            <div className="flex justify-between w-full">
              <h1>Solicitadas</h1>
              <button
                className="bg-[#3a94cc] border-none text-white rounded-[7px] text-center text-sm py-1 my-auto px-2"
                onClick={() => openModalNew()}
              >
                Nueva Ausencia
              </button>
            </div>
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
                      <div className="ausenciaType">{license.type}</div>
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

                      <div>{license.comments}</div>
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
      {showModal && (
        <div className="fixed w-full inset-0 flex justify-center items-center bg-black/50 bg-opacity-50 z-50 h-screen overflow-auto">
          <Formulario license={selectedLicense} onClose={closeModal} onUpdate={getInfo} />
        </div>
      )}
      {showModalNew && (
        <div className="fixed w-full inset-0 flex justify-center items-center bg-black/50 bg-opacity-50 z-50 h-screen overflow-auto">
          <FormularioNew onClose={closeModalNew} onUpdate={getInfo} />
        </div>
      )}
    </div>
  );
};
