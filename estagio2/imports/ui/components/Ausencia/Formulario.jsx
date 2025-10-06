import React, { useEffect, useState } from 'react';
import { toast } from 'react-toastify';
import LicenseService from '/imports/service/licenseService';
import InputFile from './InputFile';

export const Formulario = ({ license, onClose, onUpdate }) => {
  const [loadingEditSubmit, setLoadingEditSubmit] = useState(false);
  const [fileToUpload, setFileToUpload] = useState([]);
  const [filesBack, setFilesBack] = useState([]);
  const [loadingFile, setLoadingFile] = useState(true);
  const [checkStatus, setCheckStatus] = useState(false);

  useEffect(() => {
    if (license.status !== 0) {
      setCheckStatus(true);
    } else {
      setCheckStatus(false);
    }
    getFiles();
  }, []);

  const getFiles = async () => {
    try {
      const req = await LicenseService.getOne({
        id: license.id
      });
      setFilesBack(req.documents ?? 404);
    } catch (e) {
      console.log(e);
    } finally {
      setLoadingFile(false);
    }
  };
  const handleFormEditSubmit = async (event) => {
    event.preventDefault();
    setLoadingEditSubmit(true);

    const formData = new FormData(event.target);
    const typeId = parseInt(formData.get('type'));
    const comments = formData.get('comments');
    let dateStart = formData.get('dateStartDate');
    let dateEnd = formData.get('dateEndDate');
    let timeStart = formData.get('dateStartTime');
    let timeEnd = formData.get('dateEndTime');

    const filesToSend = fileToUpload ? [fileToUpload] : [];
    formData.append('files', filesToSend);

    try {
      const r = await LicenseService.edit({
        id: license.id,
        typeId: typeId,
        comments: comments || '',
        dateStart: dateStart,
        dateEnd: dateEnd,
        timeStart: timeStart,
        timeEnd: timeEnd,
        files: filesToSend
      });

      if (r.code === 200) {
        toast.success(`${r.message}`, { position: 'top-center' });
      } else {
        toast.error(`${r.message}`, { position: 'top-center' });
        return;
      }

      onUpdate();
      onClose();
    } catch (error) {
      toast.error('Error al modificar la solicitud', {
        position: 'top-center'
      });
    } finally {
      setLoadingEditSubmit(false);
    }
  };

  const handleFileSelect = (updatedFiles) => {
    setFileToUpload(updatedFiles);
  };

  const closeBtn = () => {
    onClose();
  };

  return (
    <div className="bg-white w-full max-w-lg rounded-lg shadow-lg p-4 m-2 relative h-fit">
      <button onClick={onClose} className="absolute top-2 right-2 text-gray-500">
        X
      </button>
      <h4 className="text-lg font-semibold mb-4">Modificar Ausencia</h4>
      <form onSubmit={handleFormEditSubmit}>
        <div className="mb-4">
          <label htmlFor="type" className="block text-sm font-medium text-gray-700">
            Tipo de Ausencia
          </label>
          <select
            id="type"
            name="type"
            className="mt-1 block w-full rounded-md border px-2 py-1 border-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
            defaultValue={license?.status === 1}
            disabled
          >
            <option value={license.typeId}>{license.type}</option>
          </select>
        </div>

        <div className="w-full">
          <div>
            <div className="campo">
              <label htmlFor="dateStartDate">
                Fecha de inicio
                <span style={{ color: 'red', marginLeft: '4px' }}>*</span>
              </label>
              <input
                type="date"
                id="dateStartDate"
                name="dateStartDate"
                defaultValue={license?.dateStart ?? ''}
                disabled={checkStatus}
                required
              />
            </div>
            <div className="campo">
              <label htmlFor="dateStartTime">Hora de inicio</label>
              <input
                type="time"
                id="dateStartTime"
                name="dateStartTime"
                defaultValue={license?.timeStart ?? ''}
                disabled={checkStatus}
              />
            </div>
          </div>
          <div>
            <div className="campo">
              <label htmlFor="dateEndDate">
                Fecha de finalización
                <span style={{ color: 'red', marginLeft: '4px' }}>*</span>
              </label>
              <input
                type="date"
                id="dateEndDate"
                name="dateEndDate"
                defaultValue={license?.dateEnd ?? ''}
                disabled={checkStatus}
                required
              />
            </div>
            <div className="campo">
              <label htmlFor="dateEndTime">Hora de finalización</label>
              <input
                type="time"
                id="dateEndTime"
                name="dateEndTime"
                defaultValue={license?.timeEnd ?? ''}
                disabled={checkStatus}
              />
            </div>
          </div>
        </div>

        <div className="campo">
          <label htmlFor="comments" className="block font-medium text-gray-700">
            Comentarios
          </label>
          <textarea
            id="comments"
            name="comments"
            defaultValue={license?.comments || ''}
            className="mt-1 block w-full rounded-md border px-2 py-1 border-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2"
          />
        </div>
        <div className="my-4">
          {(license.typeId === 1 || license.typeId === 2) && license.status === 1 ? (
            <>
              {loadingFile ? (
                <div className="flex w-full justify-center">
                  <span className="text-base">Cargando...</span>
                </div>
              ) : (
                <div className="flex flex-col justify-center w-full">
                  {filesBack && filesBack.length === 2 ? (
                    <div className="text-base">
                      <div className="flex flex-col items-center justify-center">
                        <span className="textRed">
                          Ya hay 2 archivos subidos: <br />
                        </span>
                      </div>
                      <div className="w-full flex flex-col items-start justify-start">
                        {filesBack.map((e) => {
                          return (
                            <div className="flex flex-col justify-center items-center">
                              <span key={e.id}>- {e.name ?? 'Desconocido'}</span>
                            </div>
                          );
                        })}
                      </div>
                    </div>
                  ) : filesBack && filesBack.length === 1 ? (
                    <div className="text-base">
                      <div className="flex flex-col items-center justify-center">
                        <span className="textRed text-center">
                          Ya subiste 1 archivo: <br />
                        </span>
                      </div>
                      <div className="flex flex-col justify-start items-start mb-2">
                        - {filesBack[0].name ?? 'Desconocido'}
                      </div>
                      <InputFile onFileSelect={handleFileSelect} maxFiles={1} />
                    </div>
                  ) : (
                    <div>
                      <InputFile onFileSelect={handleFileSelect} maxFiles={2} />
                    </div>
                  )}
                </div>
              )}
            </>
          ) : null}
        </div>

        <div className="w-full flex justify-between gap-2">
          <button
            className="w-full bg-[#dc3545] text-white py-2 rounded-md"
            onClick={() => closeBtn()}
          >
            Cancelar
          </button>
          <button
            type="submit"
            className="w-full bg-[#3a94cc] text-white py-2 rounded-md"
            disabled={loadingEditSubmit}
          >
            {loadingEditSubmit ? 'Guardando...' : 'Guardar Cambios'}
          </button>
        </div>
      </form>
    </div>
  );
};
