import React, { useEffect, useState } from "react";
import { toast } from "react-toastify";
import { FiEdit } from "react-icons/fi";
import CheckinService from "/imports/service/checkinService";

export default function PendingRecordsModal() {
    const [pendingSummary, setPendingSummary] = useState(null);
    const [openModal, setOpenModal] = useState(false);
    const [loading, setLoading] = useState(false);
    const [editableInputs, setEditableInputs] = useState({});
    const [comments, setComments] = useState({});

    useEffect(() => {
        checkPending();
    }, []);

    const checkPending = async () => {
        setLoading(true);
        try {
            const res = await CheckinService.getByJustificationStatus({
                justificationStatus: "pending",
                summaryOnly: false
            });
            setPendingSummary(res);
            // Inicializar comentarios
            const initialComments = {};
            res.data.forEach(e => {
                initialComments[e.id] = e.comments || '';
            });
            setComments(initialComments);
        } catch (error) {
            console.error('Error fetching pending summary:', error);
        } finally {
            setLoading(false);
        }
    };

    const handleEnableEdit = (id) => {
        setEditableInputs((prev) => ({ ...prev, [id]: true }));
    };

    const handleCommentChange = (id, value) => {
        setComments(prev => ({ ...prev, [id]: value }));
    };

    const handleJustifyAll = async () => {
        const entriesToJustify = Object.entries(comments);
        for (const [id, comment] of entriesToJustify) {
            console.log(`Registro ${id} justificado.`);
            try {
                await CheckinService.sendJustification({
                    registerId: id,
                    comment: comment || ""
                });
                toast.success(`Registro ${id} justificado.`);
            } catch (err) {
                toast.error(`Error al justificar registro ${id}`);
            }
        }

        setOpenModal(false);
        await checkPending();
    };

    return (
        <>
            <button
                className="px-2 font-semibold text-blue-500 hover:text-blue-600 focus:outline-none"
                onClick={() => setOpenModal(true)}
            >
                Ver
            </button>

            {openModal && (
                <div className="fixed inset-0 bg-black bg-opacity-80 flex items-center justify-center z-50">
                    <div className="bg-white p-6 rounded shadow-lg w-full mx-2 max-h-[80vh] overflow-auto">
                        {loading ? (
                            <div className="flex items-center justify-center">
                                <p>Cargando...</p>
                            </div>
                        ) : (
                            <div className="flex flex-col gap-4">
                                <p className="text-lg font-semibold">Registros pendientes de justificación</p>
                                <p className="text-sm">Total de registros: {pendingSummary.count}</p>

                                <table className="table-fixed w-full text-sm">
                                    <thead>
                                        <tr>
                                            <th className="text-left mx-2">Fecha</th>
                                            <th className="text-left mx-2">Comentarios</th>
                                            <th className="text-left mx-2">Inicio</th>
                                            <th className="text-left mx-2">Fin</th>
                                            <th className="text-center w-fit mx-2">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {pendingSummary.data.map((e) => (
                                            <tr key={e.id} className="border-t">
                                                <td className="text-left">{e.date}</td>
                                                <td className="text-left">
                                                    {editableInputs[e.id] ? (
                                                        <input
                                                            type="text"
                                                            value={comments[e.id] || ''}
                                                            onChange={(ev) => handleCommentChange(e.id, ev.target.value)}
                                                            className="border px-2 py-1 rounded w-full bg-white"
                                                        />
                                                    ) : (
                                                        <span className="text-gray-500 italic">Necesita justificación</span>
                                                    )}
                                                </td>
                                                <td className="text-left">{e.hourStart.slice(11, 16)}Hs</td>
                                                <td className="text-left">{e.hourEnd.slice(11, 16)}Hs</td>
                                                <td className="text-center w-fit">
                                                    <button
                                                        onClick={() => handleEnableEdit(e.id)}
                                                        className="text-blue-500 hover:text-blue-600"
                                                        title="Editar"
                                                    >
                                                        <FiEdit size={18} />
                                                    </button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>

                                <div className="flex justify-between mt-4">
                                    <button
                                        onClick={() => setOpenModal(false)}
                                        className="text-gray-600 hover:text-gray-800"
                                    >
                                        Cerrar
                                    </button>

                                    <button
                                        onClick={handleJustifyAll}
                                        className="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"
                                    >
                                        Justificar todos
                                    </button>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            )}
        </>
    );
}
