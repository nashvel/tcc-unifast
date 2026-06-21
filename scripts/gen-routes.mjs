import { generator, getConfig } from '@tanstack/router-generator';
const config = await getConfig({ root: process.cwd() });
await generator(config, process.cwd());
console.log('done');
