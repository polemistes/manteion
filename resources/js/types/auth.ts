export type UserRole = 'member' | 'moderator' | 'administrator';

export type User = {
    id: number;
    name: string;
    username: string;
    email: string;
    role: UserRole;
    allow_public_follow: boolean;
    share_typing: boolean;
    share_read_receipts: boolean;
    avatar_url: string | null;
    bio: string | null;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};
