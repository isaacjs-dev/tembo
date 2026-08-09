import api from './api';
import type { LoginRequest, LoginResponse, UserData } from '@/types/api';

export const authService = {
  async login(data: LoginRequest): Promise<LoginResponse> {
    const { workspace_id, ...credentials } = data;
    const response = await api.post<LoginResponse>('/auth/login', credentials, {
      headers: workspace_id ? { 'X-Workspace-Id': String(workspace_id) } : undefined,
    });
    return response.data;
  },

  async logout(): Promise<void> {
    await api.post('/auth/logout');
  },

  async me(): Promise<{ user: UserData }> {
    const response = await api.get<{ user: UserData }>('/auth/me', { timeout: 8000 });
    return response.data;
  },
};
