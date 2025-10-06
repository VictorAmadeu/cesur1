import React from 'react';
import ResponsiveLayout from '../../components/Layout';

export const NotFound  = () => {
  return (
    <ResponsiveLayout>
    <div style={{textAlign:"center"}}>
        <p style={{color:"#3A94CC", backgroundColor:"#fff"}}>Lo sentimos, no hemos encontrado lo que buscas. Vuelve a intentarlo de otra manera.</p>
    </div>
    </ResponsiveLayout>
  );
};