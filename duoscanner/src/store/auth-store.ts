import { create } from 'zustand';
import * as SecureStore from 'expo-secure-store';
import type { UserData } from '@/types/api';

interface AuthState {
  token: string | null;
  user: UserData | null;
  isAuthenticated: boolean;
  isLoading: boolean;

  setAuth: (token: string, user: UserData) => Promise<void>;
  logout: () => Promise<void>;
  setLoading: (loading: boolean) => void;
  hydrate: () => Promise<void>;
}

export const useAuthStore = create<AuthState>((set) => ({
  token: null,
  user: null,
  isAuthenticated: false,
  isLoading: true,

  setAuth: async (token, user) => {
    set({ token, user, isAuthenticated: true, isLoading: false });
    try {
      await SecureStore.setItemAsync('token', token);
      await SecureStore.setItemAsync('user', JSON.stringify(user));
    } catch (e) {
      console.error('Failed to save auth state', e);
    }
  },

  logout: async () => {
    set({ token: null, user: null, isAuthenticated: false, isLoading: false });
    try {
      await SecureStore.deleteItemAsync('token');
      await SecureStore.deleteItemAsync('user');
    } catch (e) {
      console.error('Failed to clear auth state', e);
    }
  },

  setLoading: (isLoading) => set({ isLoading }),

  hydrate: async () => {
    try {
      const token = await SecureStore.getItemAsync('token');
      const userStr = await SecureStore.getItemAsync('user');

      if (token && userStr) {
        const user = JSON.parse(userStr) as UserData;
        set({ token, user, isAuthenticated: true, isLoading: false });
      } else {
        set({ isLoading: false });
      }
    } catch {
      set({ isLoading: false });
    }
  },
}));
