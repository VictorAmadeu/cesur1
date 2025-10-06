import React from "react";
import { Bienvenida } from "./Bienvenida"
import { NavigationHome } from "./NavigationHome"

export const HomeIndex = () => {
  
    return(
        <div className="containerPrincipal">
        <main className="mainHome">
          <Bienvenida/>
          <div className="bg-[#333333] w-1/2 h-[2px] mb-10 mx-auto"></div>
          <NavigationHome />
          {/* <div className="lineaSeparacion"></div>
          <div className="sectionCuadro">
            <h2><i className="fa-solid fa-newspaper"></i> Últimas notificaciones</h2>
            <section className="noticiaPost mt-3" id='alerts'>
              <article key={1} className="noticiaActual">
              <div >
                <div className="noticiaTitulo">
                  <h3 className="p15">Noticia</h3>
                  <i className="fa-solid fa-circle-xmark" title="Cerrar notificación" style={{fontWeight:'bold', fontSize:'18px', cursor:'pointer'}}></i>
                </div>
                <div className="noticiaTexto">
                  <h3>Título noticia</h3>
                  <a style={{color: '#3a94cc', fontWeight: 'bold'}} to={{pathname:"/"}}>
                    <p style={{display: 'flex', alignItems: 'center', justifyContent: 'flex-end'}}>
                      <span>En construcción</span> <i style={{marginLeft: '5px'}} className="fa fa-arrow-circle-right" aria-hidden="true"></i>
                    </p>
                  </a>
                </div> 
              </div>
              </article>  
            </section>
          </div> */}
        </main>
      </div>
    )
}