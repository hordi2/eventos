import { apiRequest } from './client';
import { type LoginResponse } from './types';

export async function login(email: string, password: string, deviceName: string): Promise<string> {
  const response = await apiRequest<LoginResponse>('/auth/login', {
    method: 'POST',
    body: { email, password, device_name: deviceName },
  });

  return response.token;
}
