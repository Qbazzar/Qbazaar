/** Routes that draw their own page chrome: the auth pages and the post-ad wizard. */
const OWN_CHROME_PREFIXES = ['/login', '/register', '/forgot-password', '/reset-password', '/verify-otp', '/post-ad'];

/** Whether the site header and footer stay off this path. */
export function hasOwnChrome(pathname: string): boolean {
  return OWN_CHROME_PREFIXES.some((prefix) => pathname.startsWith(prefix));
}
