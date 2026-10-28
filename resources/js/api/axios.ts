import axios from 'axios';
import { Capacitor } from '@capacitor/core';

// Determine base API endpoint dynamically (supports Capacitor mobile, root domain, and /CRM subdirectory deployments)
export const getApiBase = (): string => {
  if (typeof window !== 'undefined' && localStorage.getItem('vb_crm_server_url')) {
    const customUrl = localStorage.getItem('vb_crm_server_url')!;
    if (customUrl.startsWith('https://') || customUrl.startsWith('http://localhost') || customUrl.startsWith('http://10.0.2.2')) {
      return customUrl;
    }
  }
  if (Capacitor.isNativePlatform()) {
    return (import.meta.env.VITE_API_BASE_URL as string) || 'https://vistaboundintegrated.com/CRM/api';
  }
  if (typeof window !== 'undefined' && window.location.pathname.startsWith('/CRM')) {
    return '/CRM/api';
  }
  return '/api';
};

const api = axios.create({
  baseURL: getApiBase(),
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
  timeout: 15000,
});

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('vb_crm_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response && error.response.status === 401) {
      localStorage.removeItem('vb_crm_token');
      localStorage.removeItem('vb_crm_user');
      const loginRoute = (!Capacitor.isNativePlatform() && typeof window !== 'undefined' && window.location.pathname.startsWith('/CRM'))
        ? '/CRM/login'
        : '/login';
      if (typeof window !== 'undefined' && window.location.pathname !== loginRoute) {
        window.location.href = loginRoute;
      }
    }
    return Promise.reject(error);
  }
);

export default api;
