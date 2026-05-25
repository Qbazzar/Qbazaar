/**
 * Typed client for the help center domain (Sprint 12).
 *
 * Read-only public endpoints: list categories, fetch a category with its
 * articles, fetch an article, search by free-form query. All payloads ride
 * the standard `SuccessEnvelope<T>` shape.
 */
import { isAxiosError } from 'axios';
import { api } from './client';
import { ApiClientError } from './auth';
import type {
  ErrorEnvelope,
  HelpArticle,
  HelpArticleListItem,
  HelpCategory,
  SuccessEnvelope,
} from './types';

const HELP_BASE = '/api/v1/help';

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

export async function listHelpCategories(): Promise<HelpCategory[]> {
  try {
    const { data } = await api.get<SuccessEnvelope<HelpCategory[]>>(
      `${HELP_BASE}/categories`,
    );
    return data.data;
  } catch (err) {
    throw toApiClientError(err);
  }
}

export interface HelpCategoryWithArticles extends HelpCategory {
  articles: HelpArticleListItem[];
}

export async function getHelpCategory(
  slug: string,
): Promise<HelpCategoryWithArticles> {
  try {
    const { data } = await api.get<SuccessEnvelope<HelpCategoryWithArticles>>(
      `${HELP_BASE}/categories/${encodeURIComponent(slug)}`,
    );
    return data.data;
  } catch (err) {
    throw toApiClientError(err);
  }
}

export async function getHelpArticle(slug: string): Promise<HelpArticle> {
  try {
    const { data } = await api.get<SuccessEnvelope<HelpArticle>>(
      `${HELP_BASE}/articles/${encodeURIComponent(slug)}`,
    );
    return data.data;
  } catch (err) {
    throw toApiClientError(err);
  }
}

export async function searchHelp(q: string): Promise<HelpArticleListItem[]> {
  try {
    const { data } = await api.get<SuccessEnvelope<HelpArticleListItem[]>>(
      `${HELP_BASE}/search`,
      { params: { q } },
    );
    return data.data;
  } catch (err) {
    throw toApiClientError(err);
  }
}
