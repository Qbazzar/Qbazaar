'use client';

/**
 * Resolves a lucide-react icon by the name a backend payload carries:
 * categories and help topics use PascalCase (`"Car"`), notifications kebab
 * case (`"shield-alert"`).
 *
 * Every name the API sends today renders at once. Any other name (an admin's
 * pick) loads the whole lucide set on demand, so no page ships all of lucide
 * up front; `Layers` stands in while it loads and for unknown names.
 *
 * This component is tiny but it lives in `components/ui` because every
 * categories surface uses it.
 */
import { lazy, Suspense, type ComponentType } from 'react';
import {
  BadgeCheck,
  BellRing,
  Bike,
  BookOpen,
  Briefcase,
  Car,
  Clock,
  ClockAlert,
  Download,
  Factory,
  Flag,
  House,
  Layers,
  LifeBuoy,
  Lock,
  Megaphone,
  PawPrint,
  Shield,
  ShieldAlert,
  Shirt,
  ShoppingBag,
  Smartphone,
  Sofa,
  Sparkles,
  Tag,
  Wallet,
  Wrench,
  type LucideIcon,
  type LucideProps,
} from 'lucide-react';

type IconProps = Omit<LucideProps, 'name'>;

interface Props extends IconProps {
  /** Lucide-react icon name resolved at render time (e.g. "Car", "Home", "shield-alert"). */
  name: string | null | undefined;
}

const KNOWN_ICONS = new Map<string, LucideIcon>(
  Object.entries({
    // Category seeds
    Bike,
    Briefcase,
    Car,
    Factory,
    Home: House,
    PawPrint,
    Shirt,
    Smartphone,
    Sofa,
    Wrench,
    // Help topic seeds and the help pages' default
    BookOpen,
    Lock,
    Shield,
    ShoppingBag,
    Sparkles,
    Tag,
    // Notification types
    BadgeCheck,
    BellRing,
    Clock,
    ClockAlert,
    Download,
    Flag,
    LifeBuoy,
    Megaphone,
    ShieldAlert,
    Wallet,
  }),
);

/** "shield-alert" and "ShieldAlert" both name lucide's ShieldAlert. */
function pascalCase(name: string): string {
  return name.replace(/(^|-)([a-z0-9])/g, (_match, _dash, character: string) => character.toUpperCase());
}

const IconFromFullSet = lazy(async () => {
  const registry = (await import('lucide-react')) as unknown as Record<string, ComponentType<IconProps>>;
  function IconByName({ name, ...rest }: IconProps & { name: string }) {
    const Glyph = registry[name] ?? Layers;
    return <Glyph {...rest} />;
  }
  return { default: IconByName };
});

export function DynamicIcon({ name, ...rest }: Props) {
  if (!name) return <Layers {...rest} />;
  const iconName = pascalCase(name);
  const Known = KNOWN_ICONS.get(iconName);
  if (Known) return <Known {...rest} />;
  return (
    <Suspense fallback={<Layers {...rest} />}>
      <IconFromFullSet name={iconName} {...rest} />
    </Suspense>
  );
}
