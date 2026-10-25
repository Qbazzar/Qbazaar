import { act, renderHook } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { useImageFallback } from './useImageFallback';

describe('useImageFallback', () => {
  it('starts with the first source and skips empty ones', () => {
    const { result } = renderHook(() => useImageFallback([null, 'large.jpg', undefined, 'medium.jpg']));
    expect(result.current.src).toBe('large.jpg');
  });

  it('moves down the list on each error and then has nothing left', () => {
    const { result } = renderHook(() => useImageFallback(['large.jpg', 'medium.jpg', 'large.jpg', 'original.jpg']));
    act(() => result.current.onError());
    expect(result.current.src).toBe('medium.jpg');
    act(() => result.current.onError());
    expect(result.current.src).toBe('original.jpg');
    act(() => result.current.onError());
    expect(result.current.src).toBeUndefined();
  });

  it('starts again for another photo', () => {
    const { result, rerender } = renderHook(({ list }) => useImageFallback(list), { initialProps: { list: ['a.jpg', 'b.jpg'] } });
    act(() => result.current.onError());
    rerender({ list: ['c.jpg', 'd.jpg'] });
    expect(result.current.src).toBe('c.jpg');
  });
});
