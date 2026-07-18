import type { IssuedEquipmentSummary } from '@/types/ui';

export type UserGroupSummary = {
    id: number;
    name: string;
    display_name: string;
};

export type User = {
    id: number;
    name: string;
    last_name: string | null;
    middle_name: string | null;
    email: string;
    phone: string | null;
    position: string | null;
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
    issued_equipment?: IssuedEquipmentSummary[];
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
    isSuperAdmin: boolean;
    canViewUsers: boolean;
    canImpersonateUsers: boolean;
    canAccessCompanyStructure: boolean;
    canAccessNews: boolean;
    canAccessProjects: boolean;
    canAccessChats: boolean;
    canAccessConferences: boolean;
    canAccessCalendar: boolean;
    canAccessKnowledgeBases: boolean;
    canAccessForms: boolean;
    canAccessEdo: boolean;
    canAccessFiles: boolean;
    canAccessProduction: boolean;
    canAccessWarehouses: boolean;
    canAccessEquipment: boolean;
    canAccessTsd: boolean;
    canAccessDirectories: boolean;
    canManageDirectories: boolean;
    canManageApiTokens: boolean;
    canManageWebhooks: boolean;
    canManageMessengerIntegrations: boolean;
    canManageBusinessProcesses: boolean;
    canAccessContacts: boolean;
    canAccessPersonContacts: boolean;
    canAccessCompanyContacts: boolean;
    canManageKnowledgeBases: boolean;
    canManageNews: boolean;
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
