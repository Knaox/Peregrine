/**
 * Canonical map of sidebar entry id → required Pelican permission key.
 * Shared by ServerDetailPage (sidebar filter) and ServerOverviewPage
 * (quick actions filter) so both stay consistent.
 */
export const SIDEBAR_ENTRY_PERMISSIONS: Record<string, string> = {
    console: 'control.console',
    files: 'file.read',
    sftp: 'file.sftp',
    databases: 'database.read',
    backups: 'backup.read',
    schedules: 'schedule.read',
    network: 'allocation.read',
    users: 'user.read',
};
