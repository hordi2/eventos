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

export interface CheckInScan {
  attendee_id?: number;
  ticket_id?: number;
  device_local_id: string;
  direction: 'check_in' | 'check_out';
  recorded_at: string;
}

export interface CheckInSyncResult {
  device_local_id: string;
  check_in_id: number;
  status: 'accepted' | 'conflict';
}
