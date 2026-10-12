export interface CodemodSwap {
  from: string;
  to: string;
  glyph: string | null;
  fallback: boolean;
  local: string;
}

export interface CodemodStrokeWidth {
  line: number;
  glyph: string;
  value: number | string | undefined;
  action: string;
}

export interface CodemodReport {
  file: string;
  swaps: CodemodSwap[];
  strokeWidths: CodemodStrokeWidth[];
  unmapped: string[];
  notes: string[];
}

export interface CodemodConfig {
  map: Record<string, unknown>;
  registry: Record<string, { export: string; source: string; strokes: number[] }>;
}

export function loadConfig(): CodemodConfig;
export function transformSource(source: string, filePath: string, config?: CodemodConfig): { output: string; report: CodemodReport };
