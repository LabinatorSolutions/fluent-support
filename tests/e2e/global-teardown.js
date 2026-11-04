import { evalFile, removeMailGuard } from './lib/lab.js';

export default async function globalTeardown() {
    evalFile('teardown.php');
    removeMailGuard();
}
