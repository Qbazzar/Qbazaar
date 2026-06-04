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
      dangerouslySetInnerHTML={{ __html: JSON.stringify(data) }}
    />
  );
}
