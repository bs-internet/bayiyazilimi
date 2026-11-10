import type { Option } from './option';

/** Ayarlar → API Anahtarları listesindeki anahtar (ApiTokensController@index). */
export type ApiTokenItem = {
    id: string;
    name: string;
    /** Anahtarın sahibi (yönetici); silinmiş kullanıcıda null olabilir. */
    owner: string | null;
    abilities: Option[];
    last_used_at: string | null;
    expires_at: string | null;
    expired: boolean;
    created_at: string | null;
};
