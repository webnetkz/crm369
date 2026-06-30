export type UserGroupSummary = {
    id: number;
    name: string;
    display_name: string;
};

export type User = {
    id: number;
    name: string;
    last_name: string | null;
    email: string;
    phone: string | null;
    avatar: string | null;
    background_color: string | null;
    background_image: string | null;
    background_blur: number;
    avatar_path?: string | null;
    avatar_position_x: number;
    avatar_position_y: number;
    avatar_scale: number;
    email_verified_at: string | null;
    language: string;
    has_selected_language?: boolean;
    is_super_admin?: boolean;
    group?: UserGroupSummary | null;
    user_group_id?: number | null;
    is_active?: boolean;
    deactivated_at?: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
    isSuperAdmin: boolean;
    canViewUsers: boolean;
    canImpersonateUsers: boolean;
    canManageApiTokens: boolean;
    canManageWebhooks: boolean;
    canManageKnowledgeBases: boolean;
    canAccessFunnels: boolean;
    canManageFunnels: boolean;
    isImpersonating: boolean;
    impersonator: {
        id: number;
        name: string;
        email: string;
    } | null;
};

/* @chisel-passkeys */
export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};
/* @end-chisel-passkeys */

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
