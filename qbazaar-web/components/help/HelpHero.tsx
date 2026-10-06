import type { ReactNode } from 'react';

interface HelpHeroProps {
  id: string;
  titleLead: string;
  /** Last words of the title, set in the brand colour. */
  titleAccent: string;
  subtitle: string;
  /** Search bar or other control under the subtitle. */
  children?: ReactNode;
}

/** Centred "Discover Categories" block of the all-categories screen (185:6576, 536:32620, 621:27193). */
export function HelpHero({ id, titleLead, titleAccent, subtitle, children }: HelpHeroProps) {
  return (
    <section
      aria-labelledby={id}
      className="mx-auto mt-5 mb-12 flex max-w-[1125px] flex-col items-center text-center qb-tablet:mt-2 qb-tablet:mb-8 qb-desktop:mt-6 qb-desktop:mb-12"
    >
      <h2
        id={id}
        className="font-qb text-qb-h3 font-semibold tracking-normal text-qb-ink qb-tablet:text-[36px] qb-desktop:text-qb-h1"
      >
        {titleLead} <span className="text-qb-brand">{titleAccent}</span>
      </h2>
      <p className="mt-2 max-w-[976px] text-qb-micro text-qb-ink-muted qb-tablet:mt-4 qb-tablet:text-qb-body qb-desktop:mt-2.5 qb-desktop:text-qb-h3">
        {subtitle}
      </p>
      {children ? <div className="mt-4 w-full qb-tablet:mt-6 qb-desktop:mt-8">{children}</div> : null}
    </section>
  );
}
