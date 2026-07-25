import { toast as sonner } from "vue-sonner";

export type ToastOptions = {
  description?: string;
  duration?: number;
  action?: { label: string; onClick: () => void };
};

function withAction(options?: ToastOptions) {
  if (!options?.action) return options;
  return {
    ...options,
    action: {
      label: options.action.label,
      onClick: options.action.onClick,
    },
  };
}

export function useToast() {
  return {
    success(message: string, options?: ToastOptions) {
      return sonner.success(message, withAction(options));
    },
    error(message: string, options?: ToastOptions) {
      return sonner.error(message, withAction(options));
    },
    info(message: string, options?: ToastOptions) {
      return sonner.info(message, withAction(options));
    },
    warning(message: string, options?: ToastOptions) {
      return sonner.warning(message, withAction(options));
    },
    message(message: string, options?: ToastOptions) {
      return sonner.message(message, withAction(options));
    },
    dismiss(id?: string | number) {
      sonner.dismiss(id);
    },
  };
}

export const toast = {
  success: (message: string, options?: ToastOptions) =>
    sonner.success(message, withAction(options)),
  error: (message: string, options?: ToastOptions) =>
    sonner.error(message, withAction(options)),
  info: (message: string, options?: ToastOptions) =>
    sonner.info(message, withAction(options)),
  warning: (message: string, options?: ToastOptions) =>
    sonner.warning(message, withAction(options)),
  message: (message: string, options?: ToastOptions) =>
    sonner.message(message, withAction(options)),
  dismiss: (id?: string | number) => sonner.dismiss(id),
};
