export type Appearance = 'light' | 'dark' | 'system';
export type ResolvedAppearance = 'light' | 'dark';
export type Language = 'ru' | 'en';

export type LocaleMessages = Record<string, any>;

export type Locale = {
    current: Language;
    messages: Record<Language, LocaleMessages>;
};

export type AppVariant = 'header' | 'sidebar';

export type FlashToast = {
    type: 'success' | 'info' | 'warning' | 'error';
    message: string;
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type PaginationMeta = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    has_pages: boolean;
};

export type PaginatedCollection<T> = {
    data: T[];
    meta: PaginationMeta;
    links: PaginationLink[];
};

export type Portal = {
    companyName: string;
    logoUrl: string | null;
    defaultLanguage: Language;
};

export type AppearanceSettings = {
    background_color: string | null;
    background_image_url: string | null;
    background_blur: number;
};

export type MenuCustomItem = {
    id: number;
    title: string;
    icon: string | null;
    url: string;
    opensInNewTab: boolean;
    isGlobal?: boolean;
};

export type MenuKnowledgeBaseItem = {
    id: number;
    title: string;
};

export type Menu = {
    hiddenItems: string[];
    customItems: MenuCustomItem[];
    knowledgeBases: MenuKnowledgeBaseItem[];
    order: string[];
};

export type PortalFormAvailableUser = {
    id: number;
    name: string;
    email: string;
};

export type PortalFormFieldTypeOption = {
    value: 'text' | 'textarea' | 'email' | 'number';
    label: string;
};

export type PortalFormFieldItem = {
    id: number | null;
    key: string | null;
    label: string;
    type: 'text' | 'textarea' | 'email' | 'number';
    placeholder: string | null;
    is_required: boolean;
    sort_order: number;
};

export type PortalFormListItem = {
    id: number;
    name: string;
    description: string | null;
    submission_mode: 'task' | 'chat';
    is_active: boolean;
    public_url: string;
    target_user_name: string | null;
    fields_count: number;
    submissions_count: number;
    last_submission_at: string | null;
};

export type PortalFormSubmissionItem = {
    id: number;
    created_at: string | null;
    project_task_id: number | null;
    chat_conversation_id: number | null;
    chat_message_id: number | null;
    target_user_name: string | null;
    payload: Array<{
        field_id: number;
        key: string;
        label: string;
        type: 'text' | 'textarea' | 'email' | 'number';
        value: string | null;
    }>;
};

export type PortalFormActiveItem = {
    id: number;
    name: string;
    description: string | null;
    submission_mode: 'task' | 'chat';
    is_active: boolean;
    public_url: string;
    target_user: PortalFormAvailableUser | null;
    owner: PortalFormAvailableUser | null;
    fields: PortalFormFieldItem[];
    submissions: PortalFormSubmissionItem[];
};

export type PortalFormPublicItem = {
    id: number;
    public_token: string;
    name: string;
    description: string | null;
    fields: Array<{
        id: number;
        key: string;
        label: string;
        type: 'text' | 'textarea' | 'email' | 'number';
        placeholder: string | null;
        is_required: boolean;
    }>;
};

export type FileTreeDirectory = {
    id: number;
    name: string;
    parent_id: number | null;
    can_edit: boolean;
    permission_level: 'read' | 'edit' | null;
    children_count: number;
    files_count: number;
    permissions: FileDirectoryPermissionItem[];
    children: FileTreeDirectory[];
};

export type FileDirectoryBreadcrumb = {
    id: number;
    name: string;
};

export type FileDirectoryOwner = {
    id: number;
    name: string;
    email: string;
};

export type FileEntryItem = {
    id: number;
    original_name: string;
    mime_type: string | null;
    extension: string | null;
    size_bytes: number;
    owner_name: string | null;
    created_at: string | null;
    download_url: string;
};

export type FileDirectoryPermissionItem = {
    id: number;
    access_level: 'read' | 'edit';
    subject_type: 'user' | 'group';
    subject_id: number | null;
    subject_name: string | null;
    granted_by_name: string | null;
    created_at: string | null;
};

export type FileActiveDirectory = {
    id: number;
    name: string;
    parent_id: number | null;
    owner: FileDirectoryOwner | null;
    permission_level: 'read' | 'edit' | null;
    can_edit: boolean;
    breadcrumbs: FileDirectoryBreadcrumb[];
    children: FileTreeDirectory[];
    entries: FileEntryItem[];
    permissions: FileDirectoryPermissionItem[];
    created_at: string | null;
    updated_at: string | null;
};

export type FileAvailableUser = {
    id: number;
    name: string;
    email: string;
    user_group_id: number | null;
};

export type FileAvailableGroup = {
    id: number;
    name: string;
    display_name: string;
};

export type KnowledgeBaseGroup = {
    id: number;
    name: string;
    display_name: string;
};

export type KnowledgeBaseTreeArticle = {
    id: number;
    title: string;
    slug: string;
    excerpt: string | null;
    is_published: boolean;
    sort_order: number;
    children: KnowledgeBaseTreeArticle[];
};

export type KnowledgeBaseListItem = {
    id: number;
    title: string;
    slug: string;
    description: string | null;
    is_published: boolean;
    article_count: number;
    updated_at: string | null;
    groups: KnowledgeBaseGroup[];
};

export type KnowledgeBaseBlock = {
    type: 'paragraph' | 'heading' | 'list' | 'image';
    content?: string | null;
    heading_level?: number | null;
    items?: string[];
    ordered?: boolean;
    image_path?: string | null;
    image_url?: string | null;
    image_file?: File | null;
    caption?: string | null;
};

export type KnowledgeBaseActiveBase = {
    id: number;
    title: string;
    slug: string;
    description: string | null;
    is_published: boolean;
    groups: KnowledgeBaseGroup[];
    articles: KnowledgeBaseTreeArticle[];
};

export type KnowledgeBaseActiveArticle = {
    id: number;
    knowledge_base_id: number;
    parent_id: number | null;
    title: string;
    slug: string;
    excerpt: string | null;
    sort_order: number;
    is_published: boolean;
    updated_at: string | null;
    blocks: KnowledgeBaseBlock[];
};

export type ProjectUserSummary = {
    id: number;
    name: string;
    last_name: string | null;
    email: string;
    avatar: string | null;
    avatar_scale: number;
};

export type ProjectListItem = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    is_archived: boolean;
    members_count: number;
    tasks_count: number;
    open_tasks_count: number;
    completed_tasks_count: number;
    updated_at: string | null;
    owner: ProjectUserSummary | null;
};

export type ProjectTaskListItem = {
    id: number;
    project_id: number | null;
    project_name: string | null;
    parent_task_id: number | null;
    parent_task_title: string | null;
    title: string;
    status: string;
    importance: string;
    complexity: number;
    due_at: string | null;
    completed_at: string | null;
    assignee: ProjectUserSummary | null;
    creator: ProjectUserSummary | null;
    co_assignees_count: number;
    subtasks_count: number;
    updated_at: string | null;
    subtasks: ProjectTaskListItem[];
};

export type ProjectTaskGroupProject = {
    id: number;
    name: string;
    slug: string;
    is_archived: boolean;
    owner: ProjectUserSummary | null;
    members_count: number;
};

export type ProjectTaskGroup = {
    key: string;
    kind: 'standalone' | 'project';
    title: string;
    description: string | null;
    project: ProjectTaskGroupProject | null;
    tasks_count: number;
    open_tasks_count: number;
    completed_tasks_count: number;
    tasks: ProjectTaskListItem[];
};

export type ProjectOption = {
    id: number;
    name: string;
    is_archived: boolean;
    members: ProjectUserSummary[];
};

export type ProjectActiveProject = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    is_archived: boolean;
    owner: ProjectUserSummary | null;
    members: ProjectUserSummary[];
    tasks: ProjectTaskListItem[];
};

export type ProjectActiveTask = {
    id: number;
    project_id: number | null;
    project_name: string | null;
    parent_task_id: number | null;
    parent_task: {
        id: number;
        title: string | null;
    } | null;
    title: string;
    description: string | null;
    status: string;
    importance: string;
    complexity: number;
    due_at: string | null;
    completed_at: string | null;
    sort_order: number;
    creator: ProjectUserSummary | null;
    assignee: ProjectUserSummary | null;
    co_assignees: ProjectUserSummary[];
    subtasks: ProjectTaskListItem[];
    updated_at: string | null;
};

export type ProjectTaskOption = {
    value: string;
    label: string;
};

export type ChatUserSummary = {
    id: number;
    name: string;
    email: string;
    avatar: string | null;
    avatarScale: number;
};

export type ChatConversationListItem = {
    id: number;
    type: string;
    title: string;
    subtitle: string | null;
    excerpt: string | null;
    lastMessageAt: string | null;
    unreadCount: number;
    participant: ChatUserSummary | null;
};

export type ChatMessageItem = {
    id: number;
    body: string;
    createdAt: string | null;
    isOwn: boolean;
    user: ChatUserSummary;
};

export type ChatActiveConversation = {
    id: number;
    type: string;
    title: string;
    subtitle: string | null;
    participant: ChatUserSummary | null;
    participants: ChatUserSummary[];
    messages: ChatMessageItem[];
};

export type ChatCenter = {
    unreadCount: number;
    conversations: ChatConversationListItem[];
    contacts: ChatUserSummary[];
    activeConversation: ChatActiveConversation | null;
};

export type ChatShared = {
    unreadCount: number;
};

export type PortalNotificationItem = {
    id: string;
    title: string;
    message: string;
    actionUrl: string | null;
    actionLabel: string | null;
    createdAt: string | null;
    isRead: boolean;
};

export type NotificationCenter = {
    unreadCount: number;
    items: PortalNotificationItem[];
};
