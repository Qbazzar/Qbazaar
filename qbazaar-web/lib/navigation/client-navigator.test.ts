import { afterEach, describe, expect, it, vi } from 'vitest';
import { navigateClient, registerClientNavigator } from './client-navigator';

describe('navigateClient', () => {
  let unregister: (() => void) | null = null;

  afterEach(() => {
    unregister?.();
    unregister = null;
    vi.restoreAllMocks();
  });

  it('uses the registered router navigation', () => {
    const push = vi.fn();
    unregister = registerClientNavigator(push);

    navigateClient('/account/verification?continue=%2Fpost-ad');

    expect(push).toHaveBeenCalledWith('/account/verification?continue=%2Fpost-ad');
  });

  it('stops using a navigator once it is unregistered', () => {
    const push = vi.fn();
    const assign = vi.spyOn(window.location, 'assign').mockImplementation(() => {});
    registerClientNavigator(push)();

    navigateClient('/login');

    expect(push).not.toHaveBeenCalled();
    expect(assign).toHaveBeenCalledWith('/login');
  });
});
