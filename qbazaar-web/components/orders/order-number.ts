/** Short reference of an order for people: the last eight characters of its id, upper-cased. */
export function orderNumber(id: string): string {
  return id.slice(-8).toUpperCase();
}
