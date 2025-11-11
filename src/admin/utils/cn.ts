export type ClassValue = string | number | boolean | undefined | null;

export function cn(...inputs: ClassValue[]): string {
  return inputs
    .flat()
    .filter(Boolean)
    .join(" ")
    .trim();
}