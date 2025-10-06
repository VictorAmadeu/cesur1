import React from 'react';

export const UnderConstruction = ({section}) => {
  return(
    <div style={styles.divPrincipal} className='divConstruction'>
        <img src="/images/general/en-construccion.png" style={styles.imgConstruction}/>
        <h1 style={styles.h1Construction}>{section}</h1>
        <h2 style={styles.h2Construction}>EN CONSTRUCCIÓN</h2>
        <p>Disculpen las molestias</p>
    </div>
  );
};

const styles = {
    divPrincipal: {width:"100%", maxWidth:"600px", margin:"0 auto", display:"flex", alignItems:"center", flexDirection:"column", justifyContent:"center", height:"80vh",},
    imgConstruction: {width:"100%", maxWidth:"600px", margin:"0 auto 40px",},
    h1Construction: {textTransform:"uppercase", color:"#3a94cc",},
    h2Construction: {fontSize:"32px", color:"#cc723a", fontWeight:"bold", textAlign:"center",},
  };