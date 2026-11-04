import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { adminCookies, evalFile, installMailGuard, reseed } from './lib/lab.js';

const dir = path.dirname(fileURLToPath(import.meta.url));

export default async function globalSetup() {
    // A run that died before its teardown leaves fixtures behind; clear them first.
    evalFile('teardown.php');
    installMailGuard();

    reseed();
    fs.writeFileSync(path.join(dir, '.auth/admin.json'), JSON.stringify({ cookies: adminCookies(), origins: [] }));
}
