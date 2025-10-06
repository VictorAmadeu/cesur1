import React, { useState } from 'react';
import AuthService from '/imports/service/authService';
import { ROLES } from '/imports/utils';
import ImageUploader from './ImageUploader';
import { ArrowUpRight, Images, Power } from 'lucide-react';

export const LogoHeader = ({ logo, role }) => {
	const [isOpen, setIsOpen] = useState(false);
	const [showModal, setShowModal] = useState(false);

	const logout = async () => {
		AuthService.logout();
	};

	const handleImageClick = () => {
		setIsOpen(false);
		setShowModal(true);
	};

	const closeModal = () => {
		setShowModal(false);
	};

	const onCkickAdmin = () => {
		window.open(
			'https://www.admin.intranek.com',
			'_blank',
			'noopener noreferrer'
		);
	};

	return (
		<div className='relative'>
			{/* Logo como botón para abrir el menú */}
			<div
				className='w-10 h-10 rounded-full overflow-hidden border border-gray-300 cursor-pointer'
				onClick={() => setIsOpen(!isOpen)}
			>
				<img
					src={logo?.logo_base64}
					alt={logo?.company_name || 'Logo'}
					className='w-full h-full object-cover'
				/>
			</div>

			{/* Menú desplegable */}

			{isOpen && (
				<div className='absolute right-0 w-44 bg-white shadow-lg rounded-lg border border-gray-200 z-50'>
					<label className='text-xs block w-full text-left px-2 py-2 hover:bg-gray-100 border-b border-gray-200'>
						{logo?.company_name || 'Undefined'}
					</label>
					{ROLES.includes(role) && (
						<div className='flex items-center justify-between w-full px-2 py-2 hover:bg-gray-100'>
							<button onClick={handleImageClick}>Cambiar logo</button>
							<Images />
						</div>
					)}
					{ROLES.includes(role) && (
						<div className='flex items-center justify-between w-full px-2 py-2 hover:bg-gray-100'>
							<button onClick={() => onCkickAdmin()}>Admin</button>
							<ArrowUpRight />
						</div>
					)}
					<div className='flex items-center justify-between w-full px-2 py-2 hover:bg-gray-100'>
						<button onClick={logout}>Cerrar sesión</button>
						<Power />
					</div>
				</div>
			)}
			{showModal && (
				<ImageUploader
					logo={logo.logo_base64}
					onImageChange={() => {}}
					closeModal={closeModal}
				/>
			)}
		</div>
	);
};
