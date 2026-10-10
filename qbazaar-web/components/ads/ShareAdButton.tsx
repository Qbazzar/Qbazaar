'use client';

import { useState } from 'react';
import { Share2 } from 'lucide-react';

import { Button } from '@/components/design-system/Button';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

import { ShareAdDialog } from './ShareAdDialog';

interface ShareAdButtonProps {
  title: string;
  className?: string;
}

/** "Share Ad": opens the reference's share dialog for the current page at every width. */
export function ShareAdButton({ title, className }: ShareAdButtonProps) {
  const [open, setOpen] = useState(false);
  const [url, setUrl] = useState('');

  const openDialog = () => {
    setUrl(window.location.href);
    setOpen(true);
  };

  return (
    <>
      <Button variant="outline" size="sm" onClick={openDialog} className={cn('font-normal', className)}>
        <Share2 aria-hidden />
        {t('ads.detail.share_ad')}
      </Button>
      <ShareAdDialog open={open} onOpenChange={setOpen} title={title} url={url} />
    </>
  );
}
