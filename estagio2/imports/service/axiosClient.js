import axios, { AxiosHeaders } from 'axios';
import Cookies from 'js-cookie';
import { Meteor } from 'meteor/meteor';

const axiosClient = axios.create({
  baseURL: Meteor.settings.public.baseUrl,
  timeout: 30000,
  withCredentials: true,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json'
  }
});

axiosClient.interceptors.request.use(
  (config) => {
    if (!config.headers) {
      config.headers = new AxiosHeaders();
    }
    const token = Cookies.get('tokenIntranEK');
    if (token) {
      config.headers.set
        ? config.headers.set('Authorization', `Bearer ${token}`)
        : (config.headers.Authorization = `Bearer ${token}`);
    }
    return config;
  },
  (error) => Promise.reject(error)
);

axiosClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      console.error('Sesión expirada. Por favor, inicia sesión nuevamente.');
    }
    return Promise.reject(error);
  }
);

export default axiosClient;