/** Routes inside the auth shell (app/(auth)), which draws its own header instead of the site's. */
const OWN_CHROME_PREFIXES = ['/login', '/register', '/forgot-password', '/reset-password', '/verify-otp', '/verify-email'];

/** Whether the site header and footer stay off this path. */
export function hasOwnChrome(pathname: string): boolean {
  return OWN_CHROME_PREFIXES.some((prefix) => pathname.startsWith(prefix));
}
