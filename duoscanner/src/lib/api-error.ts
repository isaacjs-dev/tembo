import axios from 'axios';
import type { ApiError } from '@/types/api';

export function getApiErrorMessage(error: unknown, fallback: string): string {
  if (!axios.isAxiosError<ApiError>(error)) {
    return fallback;
  }

  if (error.code === 'ECONNABORTED') {
    return 'O servidor demorou para responder. Verifique sua conexão e tente novamente.';
  }

  if (!error.response) {
    return 'Não foi possível estabelecer uma conexão segura com o Tembo. Verifique a internet e tente novamente.';
  }

  const data = error.response.data;
  const validationMessage = data?.errors
    ? Object.values(data.errors).flat().find(Boolean)
    : undefined;

  if (validationMessage) return validationMessage;
  if (data?.error) return data.error;

  switch (error.response.status) {
    case 401:
      return 'E-mail ou senha inválidos.';
    case 403:
      return 'Seu perfil não possui acesso ao scanner.';
    case 419:
      return 'Sua sessão expirou. Entre novamente.';
    case 429:
      return 'Muitas tentativas. Aguarde um minuto e tente novamente.';
    default:
      return data?.message || fallback;
  }
}
