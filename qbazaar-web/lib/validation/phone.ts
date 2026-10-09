// Zod-free on purpose: imported by app-wide code (phone gate, interceptors),
// so it must not drag the validation library into the shared bundle.

// Qatar mobile numbers always carry the +974 prefix followed by 8 digits.
export const qatarPhoneRegex = /^\+974[0-9]{8}$/;
