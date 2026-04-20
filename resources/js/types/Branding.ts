export interface HeaderLink {
    label: string;
    label_fr?: string;
    url: string;
    icon?: string;
    new_tab?: boolean;
    [key: string]: string | boolean | undefined;
}

export interface Branding {
    app_name: string;
    show_app_name: boolean;
    logo_height: number;
    logo_url: string;
    logo_url_light: string;
    favicon_url: string;
    header_links: HeaderLink[];
}
