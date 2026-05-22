/** Archive extensions Pelican/Wings can extract via the decompress endpoint. */
export const ARCHIVE_EXTENSIONS = ['.zip', '.tar', '.tar.gz', '.tar.bz2', '.tgz'];

/** True when `name` looks like an archive we can extract. */
export function isArchive(name: string): boolean {
    return ARCHIVE_EXTENSIONS.some((ext) => name.toLowerCase().endsWith(ext));
}
