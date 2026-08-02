import axios from 'axios';
import Constants from 'expo-constants';
import { useAuthStore } from '@/store/auth-store';

const configuredApiUrl =
  process.env.EXPO_PUBLIC_API_URL ||
  Constants.expoConfig?.extra?.apiBaseUrl ||
  'https://tembo.aracruz.org/api/v1';

/** URL can be overridden for local Expo development; production always defaults to HTTPS. */
const API_BASE_URL = String(configuredApiUrl).replace(/\/+$/, '');

if (!__DEV__ && !API_BASE_URL.startsWith('https://')) {
  throw new Error('A API do Tembo deve usar HTTPS em builds de produção.');
}

const api = axios.create({
  baseURL: API_BASE_URL,
  timeout: 30000,
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  },
});

// Request interceptor to add auth token
api.interceptors.request.use((config) => {
  const token = useAuthStore.getState().token;
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Response interceptor for auth errors
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401 && useAuthStore.getState().isAuthenticated) {
      void useAuthStore.getState().logout();
    }
    return Promise.reject(error);
  }
);

export { API_BASE_URL };
export default api;
