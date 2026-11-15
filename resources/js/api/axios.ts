import axios from 'axios';
import { Capacitor } from '@capacitor/core';
import { getApiBase, getLoginRoute } from '@/utils/basePath';

export { getApiBase };

const api = axios.create({
  baseURL: getApiBase(),
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
  timeout: 15000,
  withCredentials: !Capacitor.isNativePlatform(),
});

api.interceptors.request.use((config) => {
  // Mobile app uses Bearer personal access tokens; Web desk uses stateful session cookies
  if (Capacitor.isNativePlatform()) {
    const token = localStorage.getItem('vb_crm_token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
  }
  return config;
});

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response && error.response.status === 401) {
      localStorage.removeItem('vb_crm_token');
      localStorage.removeItem('vb_crm_user');
      const loginRoute = getLoginRoute();
      if (typeof window !== 'undefined' && window.location.pathname !== loginRoute) {
        window.location.href = loginRoute;
      }
    }
    return Promise.reject(error);
  }
);

export default api;
