import { cn } from "@/lib/utils";
import type { InputHTMLAttributes, ReactNode, TextareaHTMLAttributes, SelectHTMLAttributes } from "react";

interface FieldProps {
  label: string;
  helper?: string;
  error?: string;
  required?: boolean;
  children: ReactNode;
  className?: string;
}

export function FormField({ label, helper, error, required, children, className }: FieldProps) {
  return (
    <label className={cn("block", className)}>
      <span className="text-xs font-medium text-text flex items-center gap-1">
        {label}
        {required && <span className="text-danger">*</span>}
      </span>
      <div className="mt-1">{children}</div>
      {helper && !error && <p className="text-[11px] text-text-soft mt-1">{helper}</p>}
      {error && <p className="text-[11px] text-danger mt-1">{error}</p>}
    </label>
  );
}

export function TextInput({ className, ...props }: InputHTMLAttributes<HTMLInputElement>) {
  return (
    <input
      className={cn("h-9 w-full rounded-md border bg-input px-3 text-sm focus-ring placeholder:text-text-soft", className)}
      {...props}
    />
  );
}

export function TextArea({ className, ...props }: TextareaHTMLAttributes<HTMLTextAreaElement>) {
  return (
    <textarea
      className={cn("w-full rounded-md border bg-input px-3 py-2 text-sm focus-ring placeholder:text-text-soft min-h-[80px]", className)}
      {...props}
    />
  );
}

export function Selectish({ className, children, ...props }: SelectHTMLAttributes<HTMLSelectElement>) {
  return (
    <select
      className={cn("h-9 w-full rounded-md border bg-input px-2.5 text-sm focus-ring", className)}
      {...props}
    >
      {children}
    </select>
  );
}
