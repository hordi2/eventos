import { Model } from '@nozbe/watermelondb';
import { field, text } from '@nozbe/watermelondb/decorators';

export default class Guest extends Model {
  static table = 'guests';

  @field('event_id') eventId: number;
  @text('guest_type') guestType: 'attendee' | 'ticket';
  @field('remote_id') remoteId: number;
  @text('name') name: string;
  @text('email') email: string | null;
  @text('phone') phone: string | null;
  @field('checked_in') checkedIn: boolean;
  @text('checked_in_at') checkedInAt: string | null;
}
