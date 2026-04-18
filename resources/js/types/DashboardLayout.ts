export interface DashboardCategory {
    id: string;
    name: string;
    serverIds: number[];
}

export interface DashboardLayout {
    categories: DashboardCategory[];
    uncategorizedOrder: number[];
}
