import { API_BASE_URL } from '../config';
import { ApiError } from './ApiError';
import { type ApiErrorBody } from './types';

interface RequestOptions {
  method?: 'GET' | 'POST';
  token?: string | null;
  body?: unknown;
}

/**
 * Client HTTP minimal pour l'API de check-in (T-060) — pas de bibliothèque
 * dédiée (axios...) pour garder l'empreinte de dépendances aussi faible que
 * possible sur un poste de contrôle.
 */
export async function apiRequest<T>(path: string, options: RequestOptions = {}): Promise<T> {
  const response = await fetch(`${API_BASE_URL}${path}`, {
    method: options.method ?? 'GET',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      ...(options.token ? { Authorization: `Bearer ${options.token}` } : {}),
    },
    body: options.body !== undefined ? JSON.stringify(options.body) : undefined,
  });

  if (!response.ok) {
    const body = (await response.json().catch(() => null)) as ApiErrorBody | null;
    const message = body?.message ?? firstValidationError(body) ?? `Erreur ${response.status}.`;

    throw new ApiError(message, response.status);
  }

  return (await response.json()) as T;
}

function firstValidationError(body: ApiErrorBody | null): string | null {
  const firstField = body?.errors ? Object.values(body.errors)[0] : undefined;

  return firstField?.[0] ?? null;
}
