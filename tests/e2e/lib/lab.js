// Talks to the Docker lab through tests/bin/lab (WP-CLI), so the browser tests
// never store a password or drive wp-login.php.
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const repo = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../..');
const inLab = 'wp-content/plugins/fluent-support/tests/e2e/lab';

export const baseURL = process.env.FS_E2E_URL || 'http://127.0.0.1:8901';

function wp(args) {
    const out = execFileSync('bash', [path.join(repo, 'tests/bin/lab'), 'wp', ...args], {
        cwd: repo,
        encoding: 'utf8',
        stdio: ['ignore', 'pipe', 'pipe'],
        maxBuffer: 10 * 1024 * 1024,
    });
    return out.split('\n').filter(line => !/^\s*Container /.test(line)).join('\n').trim();
}

// Runs PHP in the lab and returns what it echoed as JSON.
export function php(code) {
    const out = wp(['eval', code]);
    const start = out.search(/[[{]/);
    if (start === -1) {
        throw new Error('No JSON from the lab:\n' + out.slice(0, 500));
    }
    return JSON.parse(out.slice(start));
}

export function evalFile(name) {
    return wp(['eval-file', `${inLab}/${name}`]);
}

// The ticket row as the database has it, for asserting what the UI changed.
export function ticket(id) {
    return php(`echo wp_json_encode(\\FluentSupport\\App\\Models\\Ticket::find(${Number(id)}));`);
}

// The ticket's thread rows of the given types, oldest first.
export function conversations(ticketId, types = ['response', 'note']) {
    const list = types.map(type => `'${type}'`).join(',');
    return php(`echo wp_json_encode(\\FluentSupport\\App\\Models\\Conversation::where('ticket_id', ${Number(ticketId)})->whereIn('conversation_type', [${list}])->orderBy('id')->get(['id', 'person_id', 'conversation_type', 'content']));`);
}

export function tagIds(ticketId) {
    return php(`echo wp_json_encode(array_map('intval', \\FluentSupport\\App\\Models\\TagPivot::where('source_type', 'ticket_tag')->where('source_id', ${Number(ticketId)})->pluck('tag_id')->all()));`);
}

export function mailSent() {
    return php(`echo wp_json_encode(get_option('fs_e2e_mail', []));`);
}

export function installMailGuard() {
    php(`wp_mkdir_p(WPMU_PLUGIN_DIR); copy(WP_PLUGIN_DIR . '/fluent-support/tests/e2e/lab/mail-guard.php', WPMU_PLUGIN_DIR . '/fs-e2e-mail-guard.php'); delete_option('fs_e2e_mail'); echo '[]';`);
    // Prove it captures before any test sends a reply.
    php(`wp_mail('guard@example.test', 'fs-e2e guard check', 'x'); echo wp_json_encode(get_option('fs_e2e_mail'));`);
    const sent = mailSent();
    if (!sent.some(mail => mail.subject === 'fs-e2e guard check')) {
        throw new Error('The E2E mail guard did not capture a test email.');
    }
}

export function removeMailGuard() {
    php(`@unlink(WPMU_PLUGIN_DIR . '/fs-e2e-mail-guard.php'); delete_option('fs_e2e_mail'); echo '[]';`);
}

// Auth and logged-in cookies for the lab administrator (user 1), valid one hour.
export function adminCookies() {
    const data = php(`$e = time() + 3600; echo wp_json_encode(['domain' => parse_url(home_url(), PHP_URL_HOST), 'cookies' => [[AUTH_COOKIE, wp_generate_auth_cookie(1, $e, 'auth')], [LOGGED_IN_COOKIE, wp_generate_auth_cookie(1, $e, 'logged_in')]]]);`);
    return data.cookies.map(([name, value]) => ({ name, value, domain: data.domain, path: '/', httpOnly: true, secure: false }));
}

// Deletes the previous fixtures and seeds a fresh set; app.js fixtures() reads it.
// Each spec file calls it first, so files do not depend on each other's changes.
export function reseed() {
    evalFile('teardown.php');
    const out = evalFile('seed.php');
    const ids = JSON.parse(out.slice(out.indexOf('{')));
    const dir = path.join(path.dirname(fileURLToPath(import.meta.url)), '../.auth');
    fs.mkdirSync(dir, { recursive: true });
    fs.writeFileSync(path.join(dir, 'fixtures.json'), JSON.stringify(ids, null, 2));
    return ids;
}
