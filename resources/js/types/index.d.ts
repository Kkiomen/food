import { InertiaLinkProps } from '@inertiajs/react';
import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

export interface SharedData {
    name: string;
    auth: Auth;
    sidebarOpen: boolean;
    [key: string]: unknown;
}

export interface ScrapRecipe {
    id: number;
    name: string;
    url: string | null;
    author: string | null;
    category: string | null;
    cuisine: string | null;
    description: string | null;
    prep_time: string | null;
    cook_time: string | null;
    total_time: string | null;
    servings: string | null;
    nutrition: Record<string, string> | null;
    ingredients: string[] | null;
    steps: { text: string; name?: string }[] | null;
    images: string[] | null;
    keywords: string[] | null;
    prepared_ingredients: Record<string, unknown>[] | null;
    prepared_steps: { text: string }[] | null;
    rating_value: string | null;
    rating_count: number | null;
    comment_count: number | null;
    diet: string | null;
    published_at: string | null;
    modified_at: string | null;
    created_at: string;
    updated_at: string;
}

export interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: { url: string | null; label: string; active: boolean }[];
}

export interface ScrapCategory {
    id: number;
    url: string;
    type: string;
    is_scraped: boolean;
    created_at: string;
    updated_at: string;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
}
