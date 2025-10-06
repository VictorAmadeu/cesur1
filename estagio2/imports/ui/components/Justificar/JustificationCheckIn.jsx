import React, { useEffect, useState } from 'react';
import CheckinService from '/imports/service/checkinService';
import { toast } from 'react-toastify';

const JustificationCheckIn = () => {
  const initialState = {
    code: 0,
    count: 0,
    hasRecords: false,
    data: [],
    message: ''
  };

  const [pendingJustifications, setPendingJustifications] = useState(initialState);
  const [loading, setLoading] = useState(true);
  const [openFormId, setOpenFormId] = useState(null);
  const [comment, setComment] = useState('');
  const [justificationType, setJustificationType] = useState(3);

  useEffect(() => {
    fetchPendingJustifications();
  }, []);

  const fetchPendingJustifications = async () => {
    const req = await CheckinService.getRegisterByStatus({
      justificationStatus: 'PENDING'
    });

    if (req.code === 200) {
      setPendingJustifications(req);
    }

    setLoading(false);
  };

  const handleJustify = async (id) => {
    if (!comment) {
      toast.error('Debes escribir un motivo para justificar', { position: 'top-center' });
      return;
    }

    setLoading(true);

    const req = await CheckinService.setJustification({
      registerId: id,
      comment,
      type: justificationType
    });

    if (req.code !== 200) {
      toast.error(req.message, { position: 'top-center' });
    }

    toast.success(req.message, { position: 'top-center' });
    // cerrar formulario y limpiar
    setOpenFormId(null);
    setComment('');
    setJustificationType(3); // volver a Hora Extra

    fetchPendingJustifications();
  };

  if (loading) return <span className="w-full flex justify-center text-center">Cargando...</span>;

  return (
    <div className="p-4 w-full max-w-3xl mx-auto">
      {loading ? (
        <span className="w-full text-center">Cargando...</span>
      ) : pendingJustifications.hasRecords ? (
        <div className="space-y-6">
          <h3 className="text-xl font-bold text-gray-700 mb-4 text-center">
            Hay {pendingJustifications.count} pendientes
          </h3>

          {pendingJustifications.data.map((item) => (
            <div
              key={item.id}
              className="flex flex-col md:flex-row justify-between bg-white shadow-lg rounded-2xl p-6 transition hover:shadow-2xl w-full"
            >
              {/* Info principal */}
              <div className="flex-1 space-y-2">
                <p className="text-lg font-semibold text-gray-800">
                  Fecha: <span className="font-normal text-gray-600">{item.date}</span>
                </p>
                <p className="text-base text-gray-700">
                  Hora inicio: <span className="font-medium">{item.hourStart}</span>
                </p>
                <p className="text-base text-gray-700">
                  Hora fin: <span className="font-medium">{item.hourEnd}</span>
                </p>
              </div>

              {/* Formulario o botón */}
              <div className="mt-4 md:mt-0 md:ml-6 flex flex-col justify-between">
                {openFormId === item.id ? (
                  <div className="flex flex-col gap-2">
                    <select
                      className="border rounded-lg p-2 text-sm mb-2 w-full"
                      value={justificationType}
                      onChange={(e) => setJustificationType(Number(e.target.value))}
                    >
                      <option value={3}>Hora Extra</option>
                      <option value={4}>Evento</option>
                    </select>

                    <textarea
                      className="w-full border rounded-lg p-2 text-sm mb-2"
                      rows={3}
                      placeholder="Escribe el motivo..."
                      value={comment}
                      onChange={(e) => setComment(e.target.value)}
                    />

                    <div className="flex gap-2">
                      <button
                        onClick={() => handleJustify(item.id)}
                        className="px-4 py-2 bg-[#3a94cc] text-white rounded-lg hover:bg-[#3a94cc]/70 transition"
                      >
                        Confirmar
                      </button>
                      <button
                        onClick={() => {
                          setOpenFormId(null);
                          setComment('');
                          setJustificationType(3);
                        }}
                        className="px-4 py-2 bg-gray-300 text-gray-800 rounded-lg hover:bg-gray-400 transition"
                      >
                        Cancelar
                      </button>
                    </div>
                  </div>
                ) : (
                  <button
                    onClick={() => setOpenFormId(item.id)}
                    className="px-4 py-2 bg-[#3a94cc] text-white rounded-lg hover:bg-[#3a94cc]/70 transition"
                  >
                    Justificar
                  </button>
                )}
              </div>
            </div>
          ))}
        </div>
      ) : (
        <span className="w-full flex justify-center text-center mt-4">Nada para justificar</span>
      )}
    </div>
  );
};

export default JustificationCheckIn;
