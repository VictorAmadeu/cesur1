// imports/ui/components/Header/LogoHeader.jsx

import React, { useState } from "react";
import AuthService from "/imports/service/authService";
import { ROLES } from "/imports/utils";
import ImageUploader from "./ImageUploader";
import { ArrowUpRight, Images, Power } from "lucide-react";

/**
 * LogoHeader
 * ----------
 * Componente responsable del bloque de la derecha del header:
 *  - Muestra el avatar de la empresa.
 *  - En escritorio (lg o más) muestra, al lado del avatar:
 *      > Nombre del empleado (línea 1).
 *      > Nombre de la empresa (línea 2, más pequeña).
 *  - Gestiona el menú desplegable con acciones (cambiar logo, admin, cerrar sesión).
 *
 * NOTA: El texto (empleado + empresa) solo se muestra en escritorio,
 *       en móvil se mantiene el comportamiento anterior (solo avatar).
 */
export const LogoHeader = ({ logo, role, employeeName, companyName }) => {
  // Controla si el menú desplegable está abierto o cerrado
  const [isOpen, setIsOpen] = useState(false);
  // Controla la visibilidad del modal para cambiar el logo
  const [showModal, setShowModal] = useState(false);

  // Valores calculados para mostrar en pantalla con fallbacks seguros
  const displayCompany = companyName || logo?.company_name || "Undefined";
  const displayEmployee = employeeName || "Usuario";
  const avatarSrc = logo?.logo_base64;

  /**
   * logout
   * ------
   * Cierra el menú (por limpieza visual) y luego llama al servicio de logout.
   */
  const logout = () => {
    setIsOpen(false);
    AuthService.logout();
  };

  /**
   * handleImageClick
   * ----------------
   * Cierra el menú y abre el modal para cambiar el logo.
   */
  const handleImageClick = () => {
    setIsOpen(false);
    setShowModal(true);
  };

  /**
   * closeModal
   * ----------
   * Cierra el modal de subida de imagen.
   */
  const closeModal = () => {
    setShowModal(false);
  };

  /**
   * onClickAdmin
   * ------------
   * Abre el panel de administración en una nueva pestaña.
   */
  const onClickAdmin = () => {
    window.open(
      "https://www.admin.intranek.com",
      "_blank",
      "noopener noreferrer"
    );
  };

  /**
   * toggleMenu
   * ----------
   * Abre o cierra el menú desplegable al hacer clic en el bloque
   * (texto + avatar).
   */
  const toggleMenu = () => {
    setIsOpen((prev) => !prev);
  };

  return (
    <div className="relative">
      {/* Bloque clicable: texto (solo desktop) + avatar */}
      <div
        className="flex items-center gap-2 cursor-pointer select-none"
        onClick={toggleMenu}
      >
        {/* Texto solo en escritorio: nombre de empleado y empresa */}
        <div className="hidden lg:flex flex-col items-end leading-tight">
          <span className="text-sm font-semibold">{displayEmployee}</span>
          <span className="text-xs uppercase tracking-wide text-slate-500">
            {displayCompany}
          </span>
        </div>

        {/* Avatar circular (se mantiene como antes) */}
        <div className="w-10 h-10 rounded-full overflow-hidden border border-gray-300">
          <img
            src={avatarSrc}
            alt={displayCompany || "Logo"}
            className="w-full h-full object-cover"
          />
        </div>
      </div>

      {/* Menú desplegable */}
      {isOpen && (
        <div className="absolute right-0 w-44 bg-white shadow-lg rounded-lg border border-gray-200 z-50">
          <label className="text-xs block w-full text-left px-2 py-2 hover:bg-gray-100 border-b border-gray-200">
            {displayCompany}
          </label>

          {/* Opciones solo para roles permitidos */}
          {ROLES.includes(role) && (
            <div className="flex items-center justify-between w-full px-2 py-2 hover:bg-gray-100">
              <button onClick={handleImageClick}>Cambiar logo</button>
              <Images />
            </div>
          )}

          {ROLES.includes(role) && (
            <div className="flex items-center justify-between w-full px-2 py-2 hover:bg-gray-100">
              <button onClick={onClickAdmin}>Admin</button>
              <ArrowUpRight />
            </div>
          )}

          {/* Cerrar sesión (disponible para cualquier rol) */}
          <div className="flex items-center justify-between w-full px-2 py-2 hover:bg-gray-100">
            <button onClick={logout}>Cerrar sesión</button>
            <Power />
          </div>
        </div>
      )}

      {/* Modal para cambiar el logo */}
      {showModal && (
        <ImageUploader
          logo={logo?.logo_base64}
          onImageChange={() => {}}
          closeModal={closeModal}
        />
      )}
    </div>
  );
};
