import { Capacitor } from '@capacitor/core';

/**
 * Known subfolder deployments supported by the application.
 */
const KNOWN_SUBFOLDERS = ['/CRM', '/vistacrm1'];

/**
 * Determine the base URL prefix for web deployment (e.g. '', '/CRM', '/vistacrm1').
 * Prioritizes import.meta.env.VITE_ROUTER_BASE if set, otherwise inspects window.location.pathname.
 */
export const getBasePath = (): string => {
  if (Capacitor.isNativePlatform()) {
    return '';
  }

  // 1. Explicit Vite environment variable override
  if (import.meta.env.VITE_ROUTER_BASE) {
    const envBase = (import.meta.env.VITE_ROUTER_BASE as string).trim().replace(/\/+$/, '');
    if (envBase && envBase !== '/') {
      return envBase.startsWith('/') ? envBase : `/${envBase}`;
    }
    return '';
  }

  // 2. Dynamic browser location detection
  if (typeof window !== 'undefined' && window.location?.pathname) {
    const pathname = window.location.pathname;
    for (const folder of KNOWN_SUBFOLDERS) {
      if (pathname === folder || pathname.startsWith(`${folder}/`)) {
        return folder;
      }
    }
  }

  return '';
};

/**
 * Return Vue Router base URL formatted with leading and trailing slashes (e.g. '/', '/CRM/', '/vistacrm1/').
 */
export const getRouterBase = (): string => {
  const base = getBasePath();
  return base ? `${base}/` : '/';
};

/**
 * Construct full URL for web assets relative to base path.
 */
export const getAssetUrl = (relativePath: string): string => {
  const cleanPath = relativePath.startsWith('/') ? relativePath.slice(1) : relativePath;
  const base = getBasePath();
  return base ? `${base}/${cleanPath}` : `/${cleanPath}`;
};

/**
 * Return the full login route path for unauthenticated redirects.
 */
export const getLoginRoute = (): string => {
  const base = getBasePath();
  return base ? `${base}/login` : '/login';
};

/**
 * Construct the full URL for Sanctum CSRF cookie initialization.
 */
export const getCsrfCookieUrl = (): string => {
  const base = getBasePath();
  return base ? `${base}/sanctum/csrf-cookie` : '/sanctum/csrf-cookie';
};

/**
 * Return the API base endpoint URL.
 * Preserves custom server URL in localStorage and native Android configuration.
 */
export const getApiBase = (): string => {
  if (typeof window !== 'undefined' && localStorage.getItem('vb_crm_server_url')) {
    const customUrl = localStorage.getItem('vb_crm_server_url')!.trim();
    const isLocalOrPrivateHttp = /^http:\/\/(localhost|127\.0\.0\.1|10\.\d{1,3}\.\d{1,3}\.\d{1,3}|192\.168\.\d{1,3}\.\d{1,3}|172\.(1[6-9]|2\d|3[0-1])\.\d{1,3}\.\d{1,3})(:\d+)?(\/.*)?$/i;
    if (customUrl.startsWith('https://') || isLocalOrPrivateHttp.test(customUrl)) {
      return customUrl.replace(/\/+$/, '');
    }
  }

  if (Capacitor.isNativePlatform()) {
    return (import.meta.env.VITE_API_BASE_URL as string) || 'https://vistaboundintegrated.com/CRM/api';
  }

  const base = getBasePath();
  return base ? `${base}/api` : '/api';
};
