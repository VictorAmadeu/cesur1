import Cookies from 'js-cookie';
import axiosClient from './axiosClient';

const AuthService = {
  login: async (credentials) => {
    try {
      const response = await axiosClient.post('/login_check', credentials);

      const { token, name } = response.data;

      let expirationDate;
      if (credentials._remember_me) {
        expirationDate = new Date();
        expirationDate.setDate(expirationDate.getDate() + 30);
      } else {
        expirationDate = new Date();
        expirationDate.setTime(expirationDate.getTime() + 8 * 60 * 60 * 1000);
      }

      Cookies.set('tokenIntranEK', token, {
        expires: expirationDate,
        secure: true
      });

      Cookies.set('name', name, {
        expires: expirationDate,
        secure: true
      });

      return response;
    } catch (error) {
      console.error('Error en login:', error.message);
      throw error;
    }
  },

  logout: () => {
    Cookies.remove('tokenIntranEK');
    Cookies.remove('permissions');
    Cookies.remove('role');
    if (process.env.NODE_ENV === 'test') {
      return;
    }
    window.location.href = '/login';
  },

  isAuthenticated: async () => {
    try {
      const response = await axiosClient.post('/global/keepAlive');
      return response.data;
    } catch (error) {
      console.log('Error', error);
      return {
        message: error.message ?? 'Error',
        code: 404
      };
    }
  },

  fetchPermissions: async () => {
    try {
      const response = await axiosClient.post('/companies/permissions');
      return response.data;
    } catch (error) {
      console.error('Error al obtener permisos:', error);
      throw error;
    }
  },

  getAllPermissions: () => {
    const permissions = Cookies.get('permissions');
    if (!permissions) {
      console.error('Permisos no disponibles.');
      return [];
    }

    try {
      return JSON.parse(permissions);
    } catch (error) {
      console.error('Error al parsear permisos:', error);
      return [];
    }
  },

  getRole: () => {
    const role = Cookies.get('role');
    if (!role) {
      console.error('Rol no disponibles.');
      return [{ message: 'Rol no disponibles.', code: 404 }];
    }

    try {
      return JSON.parse(role);
    } catch (error) {
      return [{ message: 'Error al parsear permisos' + error, code: 404 }];
    }
  },

  hasPermission: (permissionKey) => {
    const permissions = Cookies.get('permissions');
    if (!permissions) {
      console.error('Permisos no disponibles.');
      return false;
    }

    try {
      const parsedPermissions = JSON.parse(permissions);
      return !!parsedPermissions[0][permissionKey];
    } catch (error) {
      console.error('Error al parsear permisos:', error);
      return false;
    }
  },

  forgetPassword: async (email) => {
    try {
      const response = await axiosClient.post('/reset-password/request', {
        email: email
      });
      return response.data;
    } catch (error) {
      console.log(error);
    }
  },

  changePassword: async (credentials) => {
    try {
      const response = await axiosClient.post('/reset-password/change', credentials);
      return response.data;
    } catch (error) {
      console.log(error);
    }
  },

  changePasswordFirstTime: async (credentials) => {
    try {
      const response = await axiosClient.post('/reset-password/change-first', credentials);
      return response.data;
    } catch (error) {
      console.log(error);
    }
  }
};

export default AuthService;
