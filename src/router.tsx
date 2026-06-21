import { QueryClient } from "@tanstack/react-query";
import { createRouter } from "@tanstack/react-router";
import { routeTree } from "./routeTree.gen";

function PendingComponent() {
  return (
    <div className="p-6 space-y-4 animate-pulse">
      <div className="h-6 w-48 rounded bg-surface-2/60" />
      <div className="h-4 w-72 rounded bg-surface-2/40" />
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 mt-6">
        {Array.from({ length: 6 }).map((_, i) => (
          <div key={i} className="h-24 rounded-lg bg-surface-2/40" />
        ))}
      </div>
    </div>
  );
}

export const getRouter = () => {
  const queryClient = new QueryClient();

  const router = createRouter({
    routeTree,
    context: { queryClient },
    scrollRestoration: true,
    defaultPreloadStaleTime: 0,
    defaultPreload: "intent",
    defaultPendingComponent: PendingComponent,
    defaultPendingMs: 0,
    defaultPendingMinMs: 0,
  });

  return router;
};
