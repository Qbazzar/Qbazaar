'use client';

/**
 * ProfileForm — edits the authenticated user's profile.
 *
 * Initial values come from `GET /account/profile`. Submission calls
 * `PUT /account/profile`, then syncs the in-memory auth store + invalidates
 * the React Query cache so other parts of the UI (header, dashboard) pick
 * up the new name immediately.
 */
import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { Loader2Icon } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { FieldError } from '@/components/auth/FieldError';
import { t, translateMaybeKey } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';
import {
  profileSchema,
  type ProfileInput,
} from '@/lib/validation/account';
import { updateAccountProfile } from '@/lib/api/account';
import { ApiClientError } from '@/lib/api/auth';
import { AuthErrorCode } from '@/lib/api/types';
import type { AccountProfile, Language } from '@/lib/api/types';
import { useAuthStore } from '@/store/auth';

export interface ProfileFormProps {
  initial: AccountProfile;
}

export function ProfileForm({ initial }: ProfileFormProps) {
  const queryClient = useQueryClient();
  const setUser = useAuthStore((s) => s.setUser);
  const currentUser = useAuthStore((s) => s.user);

  const form = useForm<ProfileInput>({
    resolver: zodResolver(profileSchema),
    mode: 'onBlur',
    defaultValues: {
      full_name: initial.full_name,
      language: (initial.language ?? 'ar') as Language,
      bio: initial.bio ?? '',
    },
  });

  // Re-seed if the upstream query refreshes after the form has mounted.
  useEffect(() => {
    form.reset({
      full_name: initial.full_name,
      language: (initial.language ?? 'ar') as Language,
      bio: initial.bio ?? '',
    });
  }, [initial.full_name, initial.language, initial.bio, form]);

  const mutation = useMutation({
    mutationFn: updateAccountProfile,
    onSuccess: (updated) => {
      // Keep auth store in sync so the sidebar/dashboard greeting refresh.
      if (currentUser) {
        setUser({
          ...currentUser,
          full_name: updated.full_name,
          language: updated.language,
        });
      }
      queryClient.setQueryData(['account', 'profile'], updated);
      toast.success(t('account.profile.success'));
    },
  });

  const onSubmit = form.handleSubmit(async (values) => {
    try {
      await mutation.mutateAsync({
        full_name: values.full_name,
        language: values.language,
        bio: values.bio ?? null,
      });
    } catch (err) {
      handleSubmitError(err, form);
    }
  });

  const errors = form.formState.errors;
  const submitting = form.formState.isSubmitting || mutation.isPending;
  const languageValue = form.watch('language');

  return (
    <form onSubmit={onSubmit} noValidate className="max-w-2xl space-y-5">
      <div className="space-y-1.5">
        <Label htmlFor="full_name">
          {t('account.profile.full_name_label')}
        </Label>
        <Input
          id="full_name"
          type="text"
          autoComplete="name"
          placeholder={t('account.profile.full_name_placeholder')}
          aria-invalid={Boolean(errors.full_name)}
          aria-describedby={
            errors.full_name ? 'full_name-error' : undefined
          }
          className="h-10"
          {...form.register('full_name')}
        />
        <FieldError id="full_name-error" message={errors.full_name?.message} />
      </div>

      <div className="space-y-1.5">
        <Label htmlFor="language">{t('account.profile.language_label')}</Label>
        <Select
          value={languageValue}
          onValueChange={(value) =>
            form.setValue('language', value as Language, {
              shouldDirty: true,
              shouldValidate: true,
            })
          }
        >
          <SelectTrigger id="language" className="h-10 w-full">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="ar">
              {t('account.profile.language_ar')}
            </SelectItem>
            <SelectItem value="en">
              {t('account.profile.language_en')}
            </SelectItem>
          </SelectContent>
        </Select>
        <FieldError id="language-error" message={errors.language?.message} />
      </div>

      <div className="space-y-1.5">
        <Label htmlFor="bio">{t('account.profile.bio_label')}</Label>
        <Textarea
          id="bio"
          rows={4}
          maxLength={280}
          placeholder={t('account.profile.bio_placeholder')}
          aria-invalid={Boolean(errors.bio)}
          aria-describedby={errors.bio ? 'bio-error' : 'bio-hint'}
          {...form.register('bio')}
        />
        <p id="bio-hint" className="text-muted-foreground text-xs">
          {t('account.profile.bio_hint')}
        </p>
        <FieldError id="bio-error" message={errors.bio?.message} />
      </div>

      <Button
        type="submit"
        size="lg"
        disabled={submitting}
        className={cn(
          'h-11 rounded-full px-6 text-sm font-semibold',
          submitting && 'cursor-progress',
        )}
      >
        {submitting ? (
          <>
            <Loader2Icon className="size-4 animate-spin" aria-hidden />
            {t('account.profile.submitting')}
          </>
        ) : (
          t('account.profile.submit')
        )}
      </Button>
    </form>
  );
}

function handleSubmitError(
  err: unknown,
  form: ReturnType<typeof useForm<ProfileInput>>,
) {
  if (err instanceof ApiClientError) {
    if (err.code === AuthErrorCode.ValidationFailed && err.details) {
      const known: (keyof ProfileInput)[] = ['full_name', 'language', 'bio'];
      let mapped = false;
      for (const [field, messages] of Object.entries(err.details)) {
        if ((known as string[]).includes(field) && messages?.length) {
          form.setError(field as keyof ProfileInput, {
            type: 'server',
            message: messages[0],
          });
          mapped = true;
        }
      }
      if (mapped) return;
    }
    const fallback =
      translateMaybeKey(`account.errors.${err.code}`) ||
      translateMaybeKey(`auth.errors.${err.code}`) ||
      err.message;
    toast.error(fallback);
    return;
  }
  toast.error(t('auth.errors.unknown'));
}
