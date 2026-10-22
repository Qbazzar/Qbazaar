import Image from 'next/image';

interface SiteLogoProps {
  width: number;
  height: number;
  /** Above-the-fold logos load at once instead of lazily. */
  eager?: boolean;
  className?: string;
}

/** The QBazaar wordmark from the design (Q mark, BAZAAR, كيو بازار). Decorative: the parent link carries the name. */
export function SiteLogo({ width, height, eager = false, className }: SiteLogoProps) {
  return (
    <Image
      src="/brand/qb-logo.svg"
      width={width}
      height={height}
      alt=""
      loading={eager ? 'eager' : 'lazy'}
      className={className}
    />
  );
}
