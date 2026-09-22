export type User = {
    id: number;
    name: string;
    nickname: string | null;
    email: string;
    display_name: string;
    photo_url: string | null;
    is_admin: boolean;
    is_active: boolean;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};
