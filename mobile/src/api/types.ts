export interface Guest {
  guest_type: 'attendee' | 'ticket';
  id: number;
  name: string;
  email: string | null;
  phone: string | null;
  checked_in: boolean;
  checked_in_at: string | null;
}

export interface LoginResponse {
  token: string;
}

export interface ApiErrorBody {
  message?: string;
  errors?: Record<string, string[]>;
}
