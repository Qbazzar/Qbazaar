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
    // The media CDN serves Laravel's fixed conversions and cannot resize, so
    // the loader picks one of them by width. Nothing goes through
    // `/_next/image`, which keeps image work off the Node server.
    loader: "custom",
    loaderFile: "./lib/images/cdn-loader.ts",
    // The loader has two usable widths (medium to 750 px, large above), so a
    // longer list would only repeat the same two URLs in every `srcset`.
    deviceSizes: [750, 1080],
    imageSizes: [],
  },
};

// Emit the bundle treemap reports when `ANALYZE=true` (otherwise a no-op):
//   ANALYZE=true npm run build
export default withBundleAnalyzer({ enabled: process.env.ANALYZE === "true" })(
  nextConfig,
);
