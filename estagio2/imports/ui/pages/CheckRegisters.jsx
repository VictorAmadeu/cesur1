import React, { useEffect, useState } from 'react';
import JustificationRegister from '../components/Horario/JustificationRegister';

export const CheckRegistersPage = () => {
    return (
        <div className='w-full'>
            <h2 className="text-2xl font-semibold text-center text-gray-700 mt-4">Registros pendientes</h2>
            <JustificationRegister />
        </div>
    )
}