<?php

declare(strict_types=1);

namespace App\Services\Catalog\Fields;

/**
 * Merges the field definitions along a category path, root first.
 *
 * A category inherits every field of its ancestors. Declaring a field with
 * the same key replaces the inherited definition for that category and its
 * subtree (an override); a `{key, hidden: true}` marker removes it, and a
 * category lower down may declare it again. Fields are ordered by
 * `sort_order`, ties keeping ancestors first.
 */
final class FieldInheritance
{
    /**
     * @param list<array{id: string, fields: array<mixed>|null}> $path root first, the category itself last
     */
    public function resolve(array $path): ResolvedFields
    {
        /** @var array<string, ResolvedField> $visible */
        $visible = [];
        /** @var array<string, string> $hiddenBy */
        $hiddenBy = [];

        foreach ($path as $depth => $level) {
            foreach (FieldDefinitions::normalizeList($level['fields']) as $position => $definition) {
                $key = (string) $definition['key'];

                if (FieldDefinitions::isHidden($definition)) {
                    if (isset($visible[$key])) {
                        $hiddenBy[$key] = $level['id'];
                        unset($visible[$key]);
                    }

                    continue;
                }

                $declaredAbove = isset($visible[$key]) || isset($hiddenBy[$key]);
                unset($hiddenBy[$key]);

                $visible[$key] = new ResolvedField($definition, $level['id'], $declaredAbove, $depth, $position);
            }
        }

        $fields = array_values($visible);
        usort($fields, static fn (ResolvedField $a, ResolvedField $b): int => [$a->sortOrder(), $a->depth, $a->position] <=> [$b->sortOrder(), $b->depth, $b->position]);

        return new ResolvedFields($fields, $hiddenBy);
    }
}
