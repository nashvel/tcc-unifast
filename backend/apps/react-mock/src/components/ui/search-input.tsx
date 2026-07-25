import { cn } from "@/lib/utils";
import { IconSearch } from "@tabler/icons-react";
import type { InputHTMLAttributes } from "react";

export function SearchInput({ className, ...props }: InputHTMLAttributes<HTMLInputElement>) {
  return (
    <div className={cn("relative", className)}>
      <IconSearch size={15} className="absolute left-2.5 top-1/2 -translate-y-1/2 text-text-soft" />
      <input
        type="text"
        className="h-9 w-full rounded-md border bg-input pl-8 pr-3 text-sm placeholder:text-text-soft focus-ring"
        {...props}
      />
    </div>
  );
}
