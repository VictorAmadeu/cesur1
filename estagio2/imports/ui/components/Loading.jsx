import React from 'react';

export const Loading = ({text}) => {
  return(
    <div className="loading" style={{height:"100vh"}}>
        <img src="/images/general/logo.png" alt="Logo Intranek" className="loadingLogo"/>
        <img src="/images/general/loading.gif" alt="Cargando..." className="loadingGif"/>
        <p>{text ? text : null}</p>
    </div>
  );
};