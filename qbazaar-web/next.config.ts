import type { NextConfig } from "next";
import withBundleAnalyzer from "@next/bundle-analyzer";

const nextConfig: NextConfig = {
  images: {
    // Disabled until the production storage host (S3/CDN/Laravel /storage) is
    // finalised. Once known, switch to `remotePatterns` and remove this flag
    // to re-enable Vercel's image optimisation pipeline.
    unoptimized: true,
  },
};

// Emit the bundle treemap reports when `ANALYZE=true` (otherwise a no-op):
//   ANALYZE=true npm run build
export default withBundleAnalyzer({ enabled: process.env.ANALYZE === "true" })(
  nextConfig,
);
