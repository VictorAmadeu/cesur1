import React from "react";
import { useMediaQuery } from 'react-responsive';
import { DocumentosWeb } from "./DocumentosWeb";
import { DocumentoMovil } from "./DocumentosMobile";

export const DocumentosIndex = () => {
    const isMobile = useMediaQuery({ query: '(max-width: 1024px)' });
    
    return (
       isMobile ? (
           <div style={{padding: "10px"}}>
               <DocumentoMovil/>
           </div>
       ) : (
           <div style={{padding: "10px"}}>
               <DocumentosWeb/>
           </div>
       )
    );
};