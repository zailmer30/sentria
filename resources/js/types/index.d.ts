export type AuthUser = {
    id: string;
    display_name: string;
    email: string;
    locale: string;
    roles: string[];
    permissions: string[];
    is_seated_member: boolean;
    avatar_url: string | null;
    district: string | null;
};

export type Organization = {
    name: string;
    short_name: string;
    locality: string;
};

export type AppNotification = {
    id: string;
    type: string;
    category: string | null;
    priority: string;
    action_url: string | null;
    title_key: string | null;
    title_params: Record<string, string | number | null>;
    body_key: string | null;
    body_params: Record<string, string | number | null>;
    read_at: string | null;
    created_at: string | null;
};

export type PageProps = {
    auth: {
        user: AuthUser | null;
    };
    flash: {
        success?: string | null;
        error?: string | null;
    };
    notifications: {
        unread_count: number;
    };
    locale: string;
    translations: Record<string, string>;
    organization: Organization;
    ai: {
        inherits_user_permissions: boolean;
        enabled: boolean;
        transcription_low_confidence: number;
    };
};

export type NavItem = {
    key: string;
    href: string;
    labelKey: string;
    permission?: string;
    roles?: string[];
    children?: NavItem[];
};
