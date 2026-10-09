import type { NextConfig } from "next";
import withBundleAnalyzer from "@next/bundle-analyzer";

// Pages that only make sense signed in. The account layout is a client
// component and cannot export metadata, so the header covers these paths.
const PRIVATE_PATHS = ["/account/:path*", "/checkout/:path*", "/post-ad", "/impersonate"];

const nextConfig: NextConfig = {
  async headers() {
    return PRIVATE_PATHS.map((source) => ({
      source,
      headers: [{ key: "X-Robots-Tag", value: "noindex, nofollow" }],
    }));
  },
  images: {
    // Disabled until the production storage host (S3/CDN/Laravel /storage) is
    // finalised. Once known, switch to `remotePatterns` and remove this flag
    // to re-enable Next.js image optimisation.
    unoptimized: true,
  },
};

// Emit the bundle treemap reports when `ANALYZE=true` (otherwise a no-op):
//   ANALYZE=true npm run build
export default withBundleAnalyzer({ enabled: process.env.ANALYZE === "true" })(
  nextConfig,
);
