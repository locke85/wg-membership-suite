import { fileURLToPath } from 'node:url';
import path from 'node:path';
import { runBrowserQaCli } from './browserQa.mjs';

const repoRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
process.exitCode = await runBrowserQaCli(process.argv.slice(2), { repoRoot });
