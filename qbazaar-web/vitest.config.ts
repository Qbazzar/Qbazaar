import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

export default defineConfig({
  test: {
    environment: 'happy-dom',
    globals: true,
    setupFiles: ['./vitest.setup.ts'],
    // Only our own unit tests; never descend into node_modules.
    include: ['**/*.test.{ts,tsx}'],
    exclude: ['node_modules/**', 'e2e/**', '.next/**'],
  },
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./', import.meta.url)),
    },
  },
});
