import { Model } from '@nozbe/watermelondb';
import { field, text } from '@nozbe/watermelondb/decorators';

export default class PendingCheckIn extends Model {
  static table = 'pending_check_ins';

  @field('event_id') eventId: number;
  @text('guest_type') guestType: 'attendee' | 'ticket';
  @field('remote_id') remoteId: number;
  @text('device_local_id') deviceLocalId: string;
  @text('recorded_at') recordedAt: string;
  @field('synced') synced: boolean;
}
