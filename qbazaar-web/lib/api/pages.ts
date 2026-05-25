/**
 * Typed client for the public CMS pages domain (Sprint 12).
 *
 * The pages domain hosts admin-authored static content — about, terms,
 * privacy, contact — surfaced under `/p/{slug}`. Bodies are bilingual
 * markdown rendered through `MarkdownContent`.
 */
import { isAxiosError } from 'axios';
import { api } from './client';
import { ApiClientError } from './auth';
import type {
  ErrorEnvelope,
  Page,
  PageListItem,
  SuccessEnvelope,
} from './types';

const PAGES_BASE = '/api/v1/pages';

function toApiClientError(err: unknown): ApiClientError {
  if (isAxiosError<ErrorEnvelope>(err) && err.response?.data?.error) {
    const e = err.response.data.error;
    return new ApiClientError({
      status: err.response.status,
      code: e.code,
      messageKey: e.message_key,
      message: e.message,
      details: e.details,
      requestId: e.request_id,
    });
  }
  if (err instanceof Error) {
    return new ApiClientError({
      status: 0,
      code: 'NETWORK_ERROR',
      messageKey: 'errors.network',
      message: err.message,
    });
  }
  return new ApiClientError({
    status: 0,
    code: 'UNKNOWN_ERROR',
    messageKey: 'errors.unknown',
    message: 'Unknown error',
  });
}

export async function listPages(): Promise<PageListItem[]> {
  try {
    const { data } = await api.get<SuccessEnvelope<PageListItem[]>>(PAGES_BASE);
    return data.data;
  } catch (err) {
    throw toApiClientError(err);
  }
}

export async function getPage(slug: string): Promise<Page> {
  try {
    const { data } = await api.get<SuccessEnvelope<Page>>(
      `${PAGES_BASE}/${encodeURIComponent(slug)}`,
    );
    return data.data;
  } catch (err) {
    throw toApiClientError(err);
  }
}
