<?php

declare(strict_types=1);

use App\Services\Catalog\HierarchyIndex;

function sampleHierarchy(): HierarchyIndex
{
    return new HierarchyIndex(
        parents: [
            'root' => null,
            'child' => 'root',
            'grandchild' => 'child',
            'sibling' => 'root',
            'hidden' => 'root',
            'under-hidden' => 'hidden',
        ],
        active: [
            'root' => true,
            'child' => true,
            'grandchild' => true,
            'sibling' => true,
            'hidden' => false,
            'under-hidden' => true,
        ],
    );
}

it('returns the path from the root down to the node', function (): void {
    expect(sampleHierarchy()->pathTo('grandchild'))->toBe(['root', 'child', 'grandchild'])
        ->and(sampleHierarchy()->pathTo('root'))->toBe(['root'])
        ->and(sampleHierarchy()->pathTo('missing'))->toBe([]);
});

it('returns the node and every descendant', function (): void {
    expect(sampleHierarchy()->descendantsOf('root'))
        ->toEqualCanonicalizing(['root', 'child', 'grandchild', 'sibling', 'hidden', 'under-hidden'])
        ->and(sampleHierarchy()->descendantsOf('grandchild'))->toBe(['grandchild'])
        ->and(sampleHierarchy()->descendantsOf('missing'))->toBe(['missing']);
});

it('treats a node under an inactive ancestor as hidden', function (): void {
    $index = sampleHierarchy();

    expect($index->isVisible('grandchild'))->toBeTrue()
        ->and($index->isVisible('hidden'))->toBeFalse()
        ->and($index->isVisible('under-hidden'))->toBeFalse()
        ->and($index->isVisible('missing'))->toBeFalse();
});

it('only accepts visible nodes without active children as selectable leaves', function (): void {
    $index = sampleHierarchy();

    expect($index->isSelectableLeaf('grandchild'))->toBeTrue()
        ->and($index->isSelectableLeaf('sibling'))->toBeTrue()
        ->and($index->isSelectableLeaf('child'))->toBeFalse()
        ->and($index->isSelectableLeaf('root'))->toBeFalse()
        ->and($index->isSelectableLeaf('under-hidden'))->toBeFalse();
});

it('treats a node whose children are all inactive as a leaf', function (): void {
    $index = new HierarchyIndex(
        parents: ['root' => null, 'retired' => 'root'],
        active: ['root' => true, 'retired' => false],
    );

    expect($index->isSelectableLeaf('root'))->toBeTrue();
});

it('terminates on a parent cycle', function (): void {
    $index = new HierarchyIndex(parents: ['a' => 'b', 'b' => 'a'], active: []);

    expect($index->pathTo('a'))->toBe(['b', 'a'])
        ->and($index->descendantsOf('a'))->toEqualCanonicalizing(['a', 'b']);
});
