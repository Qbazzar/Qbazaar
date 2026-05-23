import Image from 'next/image';
import { Button } from '@/components/ui/button';

export default function HomePlaceholder() {
  return (
    <main className="flex min-h-screen flex-col items-center justify-center gap-8 px-6 py-16">
      <Image
        src="/brand/logo.png"
        alt="QBazaar"
        width={140}
        height={140}
        priority
        className="drop-shadow-sm"
      />

      <div className="max-w-xl text-center">
        <p className="text-coral text-xs font-bold tracking-[0.18em] uppercase">
          The Qatar marketplace
        </p>
        <h1 className="font-display mt-4 text-5xl leading-[1.05] tracking-tight text-balance md:text-6xl">
          Buy, sell, and discover<br />
          <em className="text-terracotta">right next door.</em>
        </h1>
        <p className="text-ink-700 mx-auto mt-5 max-w-md text-base leading-relaxed">
          QBazaar is in active development. The Bazzar design system is wired,
          the API speaks our contract, and Sprint 1 (Auth) is up next.
        </p>
      </div>

      <div className="flex flex-wrap items-center justify-center gap-3">
        <Button size="lg" className="rounded-full px-6">
          Coming soon
        </Button>
        <Button size="lg" variant="outline" className="rounded-full px-6">
          Read the roadmap
        </Button>
      </div>

      <div className="text-ink-500 mt-12 flex flex-wrap items-center justify-center gap-x-8 gap-y-2 text-xs">
        <span>Sprint 1 · Auth (Waves 1 + 2)</span>
        <span>·</span>
        <span>github.com/Qbazzar/Qbazaar</span>
      </div>
    </main>
  );
}
