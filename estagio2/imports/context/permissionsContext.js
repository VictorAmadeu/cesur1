import Cookies from 'js-cookie'
import React, { createContext, useContext, useEffect, useState } from 'react'
import AuthService from '../service/authService'

const PermissionsContext = createContext()

export const PermissionsProvider = ({ children }) => {
    const [permissions, setPermissions] = useState({ allowManual: false, allowDeviceRegistration: false, allowDocument: false, allowProjects: false, allowWorkSchedule: false, applyAssignedSchedule: false })
    const [role, setRole] = useState()

    const fetchPermissions = async () => {
        try {
            const req = await AuthService.fetchPermissions();
            if (req.code === 200) {
                setPermissions(req.permissions)
                setRole(req.role)
                Cookies.set('role', JSON.stringify(req.role));
                Cookies.set('permissions', JSON.stringify(req.permissions));

                return { code: req.code, message: req.message }
            } else {
                return { code: req.code, message: req.message }
            }
        } catch (error) {
            console.error('Error al obtener permisos:', error);
            return { code: 404, message: error.message }
        }
    }

    useEffect(() => {
        fetchPermissions()
    }, [])

    return (
        <PermissionsContext.Provider value={{ permissions, role, fetchPermissions }}>
            {children}
        </PermissionsContext.Provider>
    )
}

export const usePermissions = () => useContext(PermissionsContext)
