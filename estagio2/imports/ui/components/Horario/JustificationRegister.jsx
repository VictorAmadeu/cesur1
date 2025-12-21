import React, { useEffect, useState } from "react"
import CheckinService from "/imports/service/checkinService";
import { toast } from "react-toastify";
import { FiEdit } from "react-icons/fi";

const JustificationRegister = () => {
    const [pendingSummary, setPendingSummary] = useState(null);
    const [loading, setLoading] = useState(false);
    const [comments, setComments] = useState({});
    const [editableInputs, setEditableInputs] = useState({});

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

    const handleJustify = async (id) => {
        try {
            await CheckinService.sendJustification({
                registerId: id,
                comment: comments[id] || ""
            });
            toast.success(`Registro ${id} justificado.`);
            await checkPending();
        } catch (err) {
            toast.error(`Error al justificar registro ${id}`);
        }
    };

    if (loading) {
        return <span className="w-full flex justify-center items-center">Cargando registros</span>
    }

    return (
        <div className="w-full">
            {pendingSummary?.hasRecords === false ? (<span className="w-full flex justify-center items-center">No hay registros pendientes</span>) : (
                <div className="w-full">
                    <p className="text-center">Total de registros: {pendingSummary?.count ? pendingSummary.count : 0}</p>
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
                            {pendingSummary?.data?.map((e) => (
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
                                    <td className="text-center w-fit flex gap-2 justify-center">
                                        {!editableInputs[e.id] ? (
                                            <button
                                                onClick={() => handleEnableEdit(e.id)}
                                                className="text-blue-500 hover:text-blue-600"
                                                title="Editar"
                                            >
                                                <FiEdit size={18} />
                                            </button>
                                        ) : (
                                            <>
                                                <button
                                                    onClick={() => handleJustify(e.id)}
                                                    className="bg-green-500 text-white px-3 py-1 rounded hover:bg-green-600"
                                                    title="Enviar Justificación"
                                                >
                                                    Enviar
                                                </button>
                                                <button
                                                    onClick={() => setEditableInputs(prev => ({ ...prev, [e.id]: false }))}
                                                    className="bg-gray-300 text-gray-700 px-3 py-1 rounded hover:bg-gray-400"
                                                    title="Cancelar"
                                                >
                                                    Cancelar
                                                </button>
                                            </>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    )
}

export default JustificationRegister
