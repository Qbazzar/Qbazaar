/**
 * Renders a Schema.org JSON-LD block. Server-rendered into the document so
 * crawlers see it without executing JS. Accepts a single graph or an array.
 */
export function JsonLd({
  data,
}: {
  data: Record<string, unknown> | Record<string, unknown>[];
}) {
  return (
    <script
      type="application/ld+json"
      dangerouslySetInnerHTML={{ __html: serializeJsonLd(data) }}
    />
  );
}

const HTML_UNSAFE_CHARS = /[<>&\u2028\u2029]/g;

/**
 * JSON.stringify leaves "</script>" and "<!--" intact, which would let
 * user-supplied text (ad titles, descriptions) break out of the script tag.
 * Unicode escapes keep the parsed JSON value identical.
 */
export function serializeJsonLd(data: unknown): string {
  return JSON.stringify(data).replace(
    HTML_UNSAFE_CHARS,
    (char) => `\\u${char.charCodeAt(0).toString(16).padStart(4, "0")}`,
  );
}
