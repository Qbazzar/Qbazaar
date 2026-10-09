import { defineConfig, globalIgnores } from 'eslint/config';
import nextVitals from 'eslint-config-next/core-web-vitals';
import nextTs from 'eslint-config-next/typescript';

// React Compiler rules from eslint-plugin-react-hooks v7. Fixing each finding
// means changing when state/refs update, which is a behaviour decision, so
// they stay visible as warnings until each one is reviewed.
const compilerRulesAsWarnings = {
  'react-hooks/set-state-in-effect': 'warn', // mount/hydration effects that set state
  'react-hooks/refs': 'warn', // refs read during render in forms and echo hooks
  'react-hooks/immutability': 'warn', // module-level focus helper in results-focus
  'react-hooks/purity': 'warn', // Date.now() during render in OfferCard
  'react-hooks/static-components': 'warn', // dynamic icon lookup creates a component
  'react-hooks/preserve-manual-memoization': 'warn', // useMemo/useCallback deps the compiler cannot prove
};

export default defineConfig([
  ...nextVitals,
  ...nextTs,
  {
    rules: {
      ...compilerRulesAsWarnings,
      '@typescript-eslint/no-unused-vars': [
        'error',
        { argsIgnorePattern: '^_', varsIgnorePattern: '^_', caughtErrorsIgnorePattern: '^_' },
      ],
      // Avatars, blurhash previews and user uploads come from the CDN with
      // their own sizing, so next/image is a per-component decision.
      '@next/next/no-img-element': 'warn',
    },
  },
  {
    // Test doubles render a bare <img> in place of next/image.
    files: ['**/*.test.{ts,tsx}'],
    rules: { '@next/next/no-img-element': 'off', 'jsx-a11y/alt-text': 'off' },
  },
  globalIgnores(['.next/**', 'out/**', 'build/**', 'coverage/**', 'next-env.d.ts']),
]);
