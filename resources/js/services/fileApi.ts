import type { FileEntry } from '@/types/FileEntry';
import { request, requestRaw } from '@/services/http';

export async function fetchFiles(serverId: number, directory: string = '/'): Promise<FileEntry[]> {
    const params = new URLSearchParams({ directory });
    const { data } = await request<{ data: FileEntry[] }>(
        `/api/servers/${serverId}/files?${params.toString()}`,
    );
    return data;
}

export async function fetchFileContent(serverId: number, file: string): Promise<string> {
    const params = new URLSearchParams({ file });
    const response = await requestRaw(
        `/api/servers/${serverId}/files/content?${params.toString()}`,
        {
            headers: { 'Accept': 'text/plain' },
        },
    );
    return response.text();
}

export async function writeFile(serverId: number, file: string, content: string): Promise<void> {
    await request(`/api/servers/${serverId}/files/write`, {
        method: 'POST',
        body: JSON.stringify({ file, content }),
    });
}

export async function renameFiles(
    serverId: number,
    root: string,
    files: Array<{ from: string; to: string }>,
): Promise<void> {
    await request(`/api/servers/${serverId}/files/rename`, {
        method: 'POST',
        body: JSON.stringify({ root, files }),
    });
}

export async function deleteFiles(
    serverId: number,
    root: string,
    files: string[],
): Promise<void> {
    await request(`/api/servers/${serverId}/files/delete`, {
        method: 'POST',
        body: JSON.stringify({ root, files }),
    });
}

export async function compressFiles(
    serverId: number,
    root: string,
    files: string[],
): Promise<void> {
    await request(`/api/servers/${serverId}/files/compress`, {
        method: 'POST',
        body: JSON.stringify({ root, files }),
    });
}

export async function decompressFile(
    serverId: number,
    root: string,
    file: string,
): Promise<void> {
    await request(`/api/servers/${serverId}/files/decompress`, {
        method: 'POST',
        body: JSON.stringify({ root, file }),
    });
}

export async function fetchUploadUrl(serverId: number): Promise<string> {
    const { data } = await request<{ data: { url: string } }>(
        `/api/servers/${serverId}/files/upload-url`,
    );
    return data.url;
}

/**
 * Upload a batch of files straight to Wings (bypassing Peregrine's backend).
 *
 * Uses XMLHttpRequest rather than fetch() because only XHR exposes
 * `upload.onprogress`, which is what drives the percentage bar. `onProgress`
 * reports the bytes sent for THIS batch; the caller aggregates across batches.
 */
export async function uploadFilesToWings(
    uploadUrl: string,
    directory: string,
    files: File[],
    onProgress?: (loaded: number, total: number) => void,
): Promise<void> {
    const formData = new FormData();
    for (const file of files) {
        formData.append('files', file);
    }
    const url = `${uploadUrl}&directory=${encodeURIComponent(directory)}`;

    await new Promise<void>((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', url);
        xhr.upload.onprogress = (e) => {
            if (e.lengthComputable && onProgress) onProgress(e.loaded, e.total);
        };
        xhr.onload = () => {
            if (xhr.status >= 200 && xhr.status < 300) resolve();
            else reject(new Error(`Upload failed (HTTP ${xhr.status})`));
        };
        xhr.onerror = () => reject(new Error('Upload failed: network error'));
        xhr.onabort = () => reject(new Error('Upload aborted'));
        xhr.send(formData);
    });
}

export async function createFolder(
    serverId: number,
    root: string,
    name: string,
): Promise<void> {
    await request(`/api/servers/${serverId}/files/create-folder`, {
        method: 'POST',
        body: JSON.stringify({ root, name }),
    });
}

/**
 * Resolve a one-shot signed download URL for a file via Pelican's client API.
 * The returned URL is short-lived and points directly at Wings — open it in
 * a new tab (or assign to a hidden anchor) to trigger the download without
 * routing the bytes through Peregrine.
 */
export async function getFileDownloadUrl(serverId: number, file: string): Promise<string> {
    const params = new URLSearchParams({ file });
    const { data } = await request<{ data: { url: string } }>(
        `/api/servers/${serverId}/files/download?${params.toString()}`,
    );
    return data.url;
}

export async function chmodFiles(
    serverId: number,
    root: string,
    files: Array<{ file: string; mode: string }>,
): Promise<void> {
    await request(`/api/servers/${serverId}/files/chmod`, {
        method: 'POST',
        body: JSON.stringify({ root, files }),
    });
}

export async function pullFile(
    serverId: number,
    url: string,
    directory?: string,
    filename?: string,
): Promise<void> {
    await request(`/api/servers/${serverId}/files/pull`, {
        method: 'POST',
        body: JSON.stringify({ url, directory, filename }),
    });
}
