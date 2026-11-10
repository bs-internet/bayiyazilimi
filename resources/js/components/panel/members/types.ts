import type { MemberCustomerInfo, MemberOverview, PanelUser } from '@/types';

/** Müşteri detay sayfasındaki üye (panel kullanıcısı + müşteri bilgileri). */
export type MemberDetail = PanelUser & MemberCustomerInfo;

/** Üyenin kayıtlı adreslerinden biri. */
export type MemberAddress = MemberOverview['addresses'][number];
