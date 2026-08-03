import type { AxiosError } from 'axios';

export interface ExamDownloadErrorInfo {
  title: string;
  message: string;
}

/** Converts API/network failures into an actionable scanner message. */
export function explainExamDownloadError(error: unknown): ExamDownloadErrorInfo {
  const axiosError = error as AxiosError<{ error?: string; message?: string }>;
  const status = axiosError.response?.status;
  const payload = axiosError.response?.data;
  const serverMessage = payload?.error || payload?.message;

  if (!axiosError.response) {
    return { title: 'Sem conexão com a prova', message: 'Conecte-se à internet e baixe a prova antes da leitura. Depois de baixada, ela funciona offline.' };
  }
  if (status === 401) return { title: 'Sessão expirada', message: 'Entre novamente no aplicativo e tente ler o cartão.' };
  if (status === 403) return { title: 'Acesso OMR indisponível', message: serverMessage || 'Este usuário não possui a permissão ou o plano OMR necessários para esta instituição.' };
  if (status === 404) return { title: 'Prova não disponível', message: 'O QR foi lido, mas esta prova não está disponível para o usuário conectado. Use o professor responsável ou peça acesso ao administrador.' };
  if (status === 422) return { title: 'Dados da prova inválidos', message: serverMessage || 'A prova precisa ser revisada e publicada novamente.' };
  return { title: 'Erro ao carregar prova', message: serverMessage || 'Não foi possível baixar os dados da prova. Tente novamente em alguns instantes.' };
}
