import { apiClient } from './client';
import type { Tag } from '@/types/models';

interface TagsResponse {
    data: Tag[];
}

export async function getTags(flat = true): Promise<Tag[]> {
    const response = await apiClient.get<TagsResponse>('/tags', {
        params: { flat },
    });
    return response.data.data;
}

export async function getTag(id: number): Promise<Tag> {
    const response = await apiClient.get<{ data: Tag }>(`/tags/${id}`);
    return response.data.data;
}

export interface CreateTagData {
    name: string;
    color?: string;
    parent_id?: number;
}

export async function createTag(data: CreateTagData): Promise<Tag> {
    const response = await apiClient.post<{ data: Tag }>('/tags', data);
    return response.data.data;
}
