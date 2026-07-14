import { renderToString } from "vue/server-renderer";
import { createTccApp } from "./createApp";

export async function render(url: string) {
  const { app, router } = createTccApp(true);

  await router.push(url);
  await router.isReady();

  return await renderToString(app);
}
