import { authSubmitClass } from './AuthFooter';
import { cn } from '@/lib/utils';

/** Placeholder with the form's footprint while a search-param form streams in, so nothing jumps. */
export function AuthFormSkeleton({ fields }: { fields: number }) {
  return (
    <div aria-hidden="true" className="mt-8 flex animate-pulse flex-col gap-6 motion-reduce:animate-none">
      {Array.from({ length: fields }, (_, index) => (
        <div key={index} className="flex flex-col gap-2">
          <div className="h-5 w-28 rounded-qb-xs bg-qb-fill" />
          <div className="h-[52px] rounded-qb-md bg-qb-fill" />
        </div>
      ))}
      <div className={cn('rounded-qb-md bg-qb-fill', authSubmitClass)} />
    </div>
  );
}
