/**
 * Capture 1920×1080 stills and state-change recordings for the Sentria marketing film.
 *
 * This file is not run by the production pass that wrote it. Do not point it at
 * the dev database. It drives the real UI and writes votes, a motion, a
 * transcript assignment, and a minutes approval.
 *
 * One-time database (Postgres superuser), then in a shell that will serve the app:
 *
 *   sudo -u postgres createdb -O sentria sentria_marketing
 *   source marketing/demo-env.sh
 *   export SENTRIA_ORG_NAME="Sangguniang Panlalawigan"
 *   export SENTRIA_ORG_SHORT_NAME="SP"
 *   export SENTRIA_ORG_LOCALITY="Province of Demo"
 *   php artisan migrate:fresh --seeder=MarketingDemoSeeder --force
 *   php artisan serve --port=8001
 *
 * demo-env.sh currently exports Sangguniang Bayan / SB / Municipality of Malalag.
 * The three exports above put the placeholder body back before seed and serve.
 * PDFs are rendered at seed time, so the override has to be set before migrate:fresh.
 *
 * In another terminal, same env, so broadcast and queued document text can finish:
 *
 *   source marketing/demo-env.sh
 *   export SENTRIA_ORG_NAME="Sangguniang Panlalawigan"
 *   export SENTRIA_ORG_SHORT_NAME="SP"
 *   export SENTRIA_ORG_LOCALITY="Province of Demo"
 *   php artisan queue:listen --tries=1 --timeout=0
 *
 * Leave Reverb running (composer run dev). The floor, the hall, and the
 * transcript update over broadcast channels.
 *
 * Playwright is not a project dependency. From the repo root:
 *
 *   npm install --no-save playwright
 *   npx playwright install chromium
 *   node marketing/capture_snapshots.js
 *
 * Optional: APP_URL=http://localhost:8001
 * The script refuses any base URL that is not port 8001 unless SENTRIA_CAPTURE_ALLOW=1.
 *
 * Outputs:
 *   marketing/snapshots/*.png
 *   marketing/snapshots/session-ids.json
 *   marketing/snapshots/capture-report.json
 *   marketing/recordings/*.webm
 */

import { chromium } from 'playwright';
import fs from 'node:fs';
import path from 'node:path';

const ROOT = path.resolve(path.dirname(new URL(import.meta.url).pathname), '..');
const SNAP = path.join(ROOT, 'marketing', 'snapshots');
const REC = path.join(ROOT, 'marketing', 'recordings');
const BASE = process.env.APP_URL ?? 'http://localhost:8001';
const PASSWORD = 'password';
const VIEWPORT = { width: 1920, height: 1080 };

const needs = [];
const notes = [];

if (!BASE.includes(':8001') && process.env.SENTRIA_CAPTURE_ALLOW !== '1') {
    console.error(`Refusing to run against ${BASE}. Use the marketing server on port 8001.`);
    process.exit(1);
}

fs.mkdirSync(SNAP, { recursive: true });
fs.mkdirSync(REC, { recursive: true });

function shot(name) {
    return path.join(SNAP, name);
}

async function shotPage(page, name) {
    await page.screenshot({ path: shot(name), fullPage: false });
    notes.push(`saved ${name}`);
}

async function login(page, email) {
    await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
    await page.locator('#email').fill(email);
    await page.locator('#password').fill(PASSWORD);
    await page.getByRole('button', { name: 'Sign in' }).click();
    await page.waitForURL((url) => !url.pathname.endsWith('/login'), { timeout: 30000 });
    await page.locator('[data-page]').first().waitFor({ timeout: 30000 });
}

async function storageFor(browser, email) {
    const context = await browser.newContext({ viewport: VIEWPORT });
    const page = await context.newPage();
    await login(page, email);
    const state = await context.storageState();
    await context.close();
    return state;
}

async function openPage(browser, state, url) {
    const context = await browser.newContext({ viewport: VIEWPORT, storageState: state });
    const page = await context.newPage();
    if (url) {
        await page.goto(url, { waitUntil: 'domcontentloaded' });
        await page.locator('[data-page]').first().waitFor({ timeout: 30000 });
    }
    return { context, page };
}

async function recordPage(browser, state, url) {
    const context = await browser.newContext({
        viewport: VIEWPORT,
        storageState: state,
        recordVideo: { dir: REC, size: VIEWPORT },
    });
    const page = await context.newPage();
    await page.goto(url, { waitUntil: 'domcontentloaded' });
    await page.locator('[data-page]').first().waitFor({ timeout: 30000 });
    return { context, page };
}

async function finishRecording(handle, filename) {
    const video = handle.page.video();
    await handle.page.close();
    await video.saveAs(path.join(REC, filename));
    await handle.context.close();
    notes.push(`saved ${filename}`);
}

async function setTheme(page, theme) {
    const label = theme === 'dark' ? 'Switch to dark theme' : 'Switch to light theme';
    const button = page.getByRole('button', { name: label });
    if (await button.count()) {
        await button.click();
        await page.waitForFunction((expected) => document.documentElement.dataset.theme === expected, theme);
    }
}

async function sessionHref(page, title) {
    await page.goto(`${BASE}/sessions`, { waitUntil: 'domcontentloaded' });
    const link = page.getByRole('link', { name: title }).first();
    await link.waitFor({ timeout: 20000 });
    const href = await link.getAttribute('href');
    if (!href) {
        throw new Error(`No href for ${title}`);
    }
    const id = href.split('/sessions/')[1]?.split(/[/?#]/)[0];
    if (!id) {
        throw new Error(`Could not read a session id from ${href}`);
    }
    return { href, id };
}

function visibleText(page, text) {
    return page.getByText(text, { exact: false }).locator('visible=true').first();
}

async function waitForText(page, text, timeout = 200000) {
    await visibleText(page, text).waitFor({ timeout });
}

function visibleRole(page, role, name, options = {}) {
    return page.getByRole(role, { name, exact: options.exact }).locator('visible=true').first();
}

async function clickVisible(page, role, name, options = {}) {
    await visibleRole(page, role, name, options).click();
}

async function openMemberPdf(page) {
    if ((await visibleRole(page, 'button', 'Close full text').count()) === 0) {
        const open = visibleRole(page, 'button', 'Open full text');
        if ((await open.count()) === 0) {
            needs.push('member-floor: Open full text was not on screen. NEEDS DEMO CONTENT for the PDF.');
            return false;
        }

        await open.click();
        await visibleRole(page, 'button', 'Close full text').waitFor({ timeout: 20000 });
    }

    try {
        await page.getByText('Annotate', { exact: true }).locator('visible=true').first().waitFor({ timeout: 30000 });
        return true;
    } catch {
        needs.push('member-floor: PDF viewer did not show the Annotate toolbar. NEEDS DEMO CONTENT for the PDF.');
        return false;
    }
}

async function showMyNotes(page, { reopen = false } = {}) {
    const heading = visibleRole(page, 'heading', 'My notes');

    if (reopen) {
        const hide = visibleRole(page, 'button', 'Hide notes');
        if (await hide.count()) {
            await hide.click();
        }

        const open = visibleRole(page, 'button', 'My notes');
        await open.waitFor({ timeout: 10000 });
        await open.click();
    } else if ((await heading.count()) === 0) {
        await clickVisible(page, 'button', 'My notes');
    }

    await heading.waitFor({ timeout: 20000 });
    await waitForText(page, 'twenty-slot floor');
}

async function tryHighlight(page) {
    if (!(await openMemberPdf(page))) {
        return false;
    }

    await page.getByText('Annotate', { exact: true }).locator('visible=true').first().click();

    const ink = visibleRole(page, 'button', 'Ink Highlighter');
    const highlight = visibleRole(page, 'button', 'Highlight');
    try {
        await ink.waitFor({ timeout: 8000 });
    } catch {
        try {
            await highlight.waitFor({ timeout: 8000 });
        } catch {
            needs.push('member-floor-highlight: EmbedPDF highlight control was not found after opening the PDF. NEEDS DEMO CONTENT for the stroke.');
            return false;
        }
    }

    if (await ink.count()) {
        await ink.click();
    } else {
        await highlight.click();
    }

    const pane = page.locator('#document-content');
    const box = await pane.boundingBox();
    if (!box) {
        needs.push('member-floor-highlight: PDF surface had no box to drag. NEEDS DEMO CONTENT for the stroke.');
        return false;
    }
    const start = { x: Math.round(box.width * 0.45), y: Math.round(box.height * 0.3) };
    const end = { x: Math.round(box.width * 0.58), y: Math.round(box.height * 0.3) };
    await pane.hover({ position: start });
    await page.mouse.down();
    await pane.hover({ position: end });
    await page.mouse.up();
    try {
        await page.getByText(/Marks saved|Saving your marks/i).first().waitFor({ timeout: 6000 });
    } catch {
        needs.push('member-floor-highlight: drag did not save a mark. The Annotate tab was opened; check the still for a stroke.');
    }
    await page.waitForTimeout(800);
    return true;
}

const browser = await chromium.launch({ headless: true });

try {
    const secretariatState = await storageFor(browser, 'secretariat@sentria.test');
    const memberState = await storageFor(browser, 'member@sentria.test');
    const presidingState = await storageFor(browser, 'presiding@sentria.test');

    const finder = await openPage(browser, secretariatState, `${BASE}/sessions`);
    const live = await sessionHref(finder.page, '38th Regular Session');
    const past = await sessionHref(finder.page, '37th Regular Session');
    await finder.context.close();

    const liveFloor = `${BASE}/sessions/${live.id}/floor/member`;
    const secretariatFloor = `${BASE}/sessions/${live.id}/floor/secretariat`;
    const hallUrl = `${BASE}/sessions/${live.id}/floor/dashboard`;
    const liveTranscript = `${BASE}/sessions/${live.id}/transcript`;
    const pastTranscript = `${BASE}/sessions/${past.id}/transcript`;

    const secretariat = await openPage(browser, secretariatState, secretariatFloor);
    await waitForText(secretariat.page, 'Quorum not met');
    await shotPage(secretariat.page, 'quorum-short.png');
    await shotPage(secretariat.page, 'secretariat-console.png');

    const quorumRec = await recordPage(browser, secretariatState, secretariatFloor);
    await waitForText(quorumRec.page, 'Quorum not met');

    const member = await openPage(browser, memberState, liveFloor);
    await waitForText(member.page, 'Raise a motion');

    try {
        await waitForText(quorumRec.page, 'Quorum met', 15000);
    } catch {
        notes.push('Quorum did not arrive over the broadcast. Reloading the secretariat page.');
        await quorumRec.page.reload({ waitUntil: 'domcontentloaded' });
        await waitForText(quorumRec.page, 'Quorum met');
        await secretariat.page.reload({ waitUntil: 'domcontentloaded' });
    }
    await finishRecording(quorumRec, 'rec-quorum-checkin.webm');
    await secretariat.page.reload({ waitUntil: 'domcontentloaded' });
    await waitForText(secretariat.page, 'Quorum met');
    await shotPage(secretariat.page, 'quorum-met.png');

    await shotPage(member.page, 'member-floor-clean.png');

    const highlightRec = await recordPage(browser, memberState, liveFloor);
    const drew = await tryHighlight(highlightRec.page);
    await finishRecording(highlightRec, 'rec-member-highlight.webm');
    if (drew) {
        await tryHighlight(member.page);
    }
    await shotPage(member.page, 'member-floor-highlight.png');

    const notesRec = await recordPage(browser, memberState, liveFloor);
    await openMemberPdf(notesRec.page);
    await showMyNotes(notesRec.page, { reopen: true });
    await finishRecording(notesRec, 'rec-member-notes.webm');
    await openMemberPdf(member.page);
    await showMyNotes(member.page);
    await shotPage(member.page, 'member-notes.png');

    const assistantRec = await recordPage(browser, memberState, liveFloor);
    await clickVisible(assistantRec.page, 'button', 'Assistant');
    await waitForText(assistantRec.page, 'Verify all AI output against official records');
    await finishRecording(assistantRec, 'rec-assistant-open.webm');
    await clickVisible(member.page, 'button', 'Assistant');
    await waitForText(member.page, 'Verify all AI output against official records');
    await shotPage(member.page, 'member-assistant.png');

    const presiding = await openPage(browser, presidingState, liveFloor);
    await waitForText(presiding.page, 'Raise a motion');
    await shotPage(presiding.page, 'presiding-same-floor.png');
    await presiding.context.close();

    const hall = await openPage(browser, secretariatState, hallUrl);
    const hallRec = await recordPage(browser, secretariatState, hallUrl);
    await secretariat.page.getByRole('button', { name: 'View document' }).click();
    try {
        await waitForText(hallRec.page, 'Scholarship Program', 100000);
    } catch {
        notes.push('Hall document title did not arrive over the broadcast. Reloading.');
        await hallRec.page.reload({ waitUntil: 'domcontentloaded' });
        await waitForText(hallRec.page, 'Scholarship Program');
    }
    await finishRecording(hallRec, 'rec-hall-document.webm');
    await hall.page.reload({ waitUntil: 'domcontentloaded' });
    await waitForText(hall.page, 'Scholarship Program');
    await shotPage(hall.page, 'hall-document-light.png');
    await setTheme(hall.page, 'dark');
    await shotPage(hall.page, 'hall-document-dark.png');
    await setTheme(hall.page, 'light');
    await shotPage(secretariat.page, 'secretariat-pdf-sent.png');

    const motionRec = await recordPage(browser, memberState, liveFloor);
    await clickVisible(motionRec.page, 'button', 'Raise a motion');
    await waitForText(motionRec.page, 'seeks the floor');
    await finishRecording(motionRec, 'rec-member-motion.webm');

    const hallMotion = await recordPage(browser, secretariatState, hallUrl);
    try {
        await waitForText(hallMotion.page, 'seeks the floor', 15000);
    } catch {
        await hallMotion.page.reload({ waitUntil: 'domcontentloaded' });
        await waitForText(hallMotion.page, 'seeks the floor');
    }
    await finishRecording(hallMotion, 'rec-hall-motion.webm');
    await member.page.reload({ waitUntil: 'domcontentloaded' });
    await waitForText(member.page, 'seeks the floor');
    await shotPage(member.page, 'member-motion-dock.png');
    await hall.page.reload({ waitUntil: 'domcontentloaded' });
    await waitForText(hall.page, 'seeks the floor');
    await shotPage(hall.page, 'hall-motion-light.png');
    await setTheme(hall.page, 'dark');
    await shotPage(hall.page, 'hall-motion-dark.png');
    await setTheme(hall.page, 'light');

    await secretariat.page.reload({ waitUntil: 'domcontentloaded' });
    const spoken = secretariat.page.getByLabel('Record the motion as spoken');
    await spoken.waitFor({ timeout: 20000 });
    await spoken.fill('I move that the body approve the scholarship ordinance on second reading.');
    await shotPage(secretariat.page, 'secretariat-motion-form.png');
    await secretariat.page.getByRole('button', { name: 'Submit motion' }).click();
    await waitForText(secretariat.page, 'Open voting');
    await secretariat.page.getByRole('button', { name: 'Open voting' }).click();
    await waitForText(secretariat.page, 'Voting in progress');

    const offlineRec = await recordPage(browser, memberState, liveFloor);
    await offlineRec.context.setOffline(true);
    await waitForText(offlineRec.page, 'Offline — cached agenda and documents remain available.');
    await clickVisible(offlineRec.page, 'button', 'Yes', { exact: true });
    await waitForText(offlineRec.page, 'ballot(s) waiting to sync');
    await offlineRec.page.screenshot({ path: shot('member-offline-queued.png'), fullPage: false });
    notes.push('saved member-offline-queued.png');
    await offlineRec.context.setOffline(false);
    try {
        await waitForText(offlineRec.page, 'Syncing queued ballots', 8000);
    } catch {
        notes.push('Syncing copy was faster than the waiter. Waiting for the queue line to clear.');
    }
    await offlineRec.page.getByText('ballot(s) waiting to sync').first().waitFor({ state: 'hidden', timeout: 20000 }).catch(() => {
        needs.push('offline sync: queued-ballot line did not clear. NEEDS DEMO CONTENT if the still shows a queue.');
    });
    await finishRecording(offlineRec, 'rec-offline-sync.webm');
    await member.page.reload({ waitUntil: 'domcontentloaded' });
    await shotPage(member.page, 'member-synced.png');

    const hallVote = await recordPage(browser, secretariatState, hallUrl);
    try {
        await waitForText(hallVote.page, 'Voting in progress', 15000);
    } catch {
        await hallVote.page.reload({ waitUntil: 'domcontentloaded' });
        await waitForText(hallVote.page, 'Voting in progress');
    }
    await finishRecording(hallVote, 'rec-hall-vote.webm');
    await hall.page.reload({ waitUntil: 'domcontentloaded' });
    await waitForText(hall.page, 'Voting in progress');
    await shotPage(hall.page, 'hall-vote-light.png');
    await setTheme(hall.page, 'dark');
    await shotPage(hall.page, 'hall-vote-dark.png');
    await setTheme(hall.page, 'light');

    const transcript = await openPage(browser, secretariatState, liveTranscript);
    await waitForText(transcript.page, 'Will the sponsor yield');
    await shotPage(transcript.page, 'transcript-live-feed.png');
    await shotPage(transcript.page, 'transcript-unassigned.png');
    const low = transcript.page.getByText('How many slots are funded', { exact: false }).first();
    await low.waitFor();
    await shotPage(transcript.page, 'transcript-low-confidence.png');

    const assignRec = await recordPage(browser, secretariatState, liveTranscript);
    const yieldRow = assignRec.page.locator('li, article, div').filter({ hasText: 'Will the sponsor yield' }).last();
    await yieldRow.getByRole('button', { name: 'Correct segment' }).click();
    await assignRec.page.getByRole('dialog').getByRole('combobox').click();
    await assignRec.page.getByRole('option', { name: 'Ramon Delos Santos' }).click();
    await assignRec.page.getByRole('button', { name: 'Save correction' }).click();
    await waitForText(assignRec.page, 'Ramon Delos Santos');
    await finishRecording(assignRec, 'rec-transcript-assign.webm');
    await transcript.page.reload({ waitUntil: 'domcontentloaded' });
    await waitForText(transcript.page, 'Ramon Delos Santos');
    await shotPage(transcript.page, 'transcript-assigned.png');

    const pastPage = await openPage(browser, secretariatState, pastTranscript);
    await pastPage.page.getByRole('tab', { name: 'Corrections' }).click();
    await waitForText(pastPage.page, 'as amended');
    await shotPage(pastPage.page, 'transcript-37-correction.png');
    const correctRec = await recordPage(browser, secretariatState, pastTranscript);
    await correctRec.page.getByRole('tab', { name: 'Corrections' }).click();
    await waitForText(correctRec.page, 'as amended');
    const amended = correctRec.page.locator('li, article, div').filter({ hasText: 'as amended' }).last();
    if (await amended.getByRole('button', { name: 'Correct segment' }).count()) {
        await amended.getByRole('button', { name: 'Correct segment' }).click();
        await waitForText(correctRec.page, 'as I mended');
    }
    await finishRecording(correctRec, 'rec-transcript-correct.webm');

    await pastPage.page.goto(`${BASE}/minutes`, { waitUntil: 'domcontentloaded' });
    await pastPage.page.getByRole('link', { name: '37th Regular Session' }).first().click();
    await waitForText(pastPage.page, 'AI Draft');
    await waitForText(pastPage.page, 'YES:');
    const minutesUrl = pastPage.page.url();
    const minutesId = minutesUrl.split('/minutes/')[1]?.split(/[/?#]/)[0] ?? null;
    await shotPage(pastPage.page, 'minutes-draft.png');

    const approveRec = await recordPage(browser, secretariatState, minutesUrl);
    await waitForText(approveRec.page, 'AI Draft');
    await approveRec.page.getByRole('button', { name: 'Accept for secretariat review' }).click();
    await approveRec.page.getByRole('link', { name: 'Edit minutes' }).click();
    const content = approveRec.page.locator('#content');
    await content.waitFor();
    const existing = await content.inputValue();
    await content.fill(`${existing}\n\nThe secretary confirms these tallies match the ballots.`);
    await approveRec.page.getByRole('button', { name: 'Save' }).click();
    await waitForText(approveRec.page, 'Mark reviewed');
    await approveRec.page.getByRole('button', { name: 'Mark reviewed' }).click();
    await finishRecording(approveRec, 'rec-minutes-approve.webm');

    const officerMinutes = await openPage(browser, presidingState, minutesUrl);
    await officerMinutes.page.getByRole('button', { name: 'Approve' }).click();
    await waitForText(officerMinutes.page, 'Approval');
    await shotPage(officerMinutes.page, 'minutes-approved.png');
    await officerMinutes.context.close();

    const compare = await openPage(browser, secretariatState, `${BASE}/ai/compare`);
    const compareRec = await recordPage(browser, secretariatState, `${BASE}/ai/compare`);
    async function pick(page, label, option) {
        await page.getByRole('combobox', { name: label }).click();
        await page.getByRole('option', { name: option }).click();
    }
    await pick(compareRec.page, 'Document A', /Provincial Scholarship Program/);
    await pick(compareRec.page, 'Document B', /Tricycles on Provincial Roads/);
    await compareRec.page.getByRole('button', { name: 'Compare', exact: true }).click();
    try {
        await waitForText(compareRec.page, 'Section changes', 20000);
    } catch {
        needs.push('ai-compare: result page did not show section changes. NEEDS DEMO CONTENT.');
    }
    await finishRecording(compareRec, 'rec-ai-compare.webm');
    await pick(compare.page, 'Document A', /Provincial Scholarship Program/);
    await pick(compare.page, 'Document B', /Tricycles on Provincial Roads/);
    await compare.page.getByRole('button', { name: 'Compare', exact: true }).click();
    await shotPage(compare.page, 'ai-compare.png');

    const audit = await openPage(browser, secretariatState, `${BASE}/audit`);
    await waitForText(audit.page, 'Append-only hash-chained audit trail');
    await shotPage(audit.page, 'audit-chain.png');

    const portal = await browser.newContext({ viewport: VIEWPORT });
    const portalPage = await portal.newPage();
    await portalPage.goto(`${BASE}/portal`, { waitUntil: 'domcontentloaded' });
    await waitForText(portalPage, 'Recently published');
    await shotPage(portalPage, 'portal-home.png');
    await portalPage.getByRole('link', { name: /Disaster Risk Reduction/ }).first().click();
    await portalPage.locator('[data-page]').first().waitFor();
    await shotPage(portalPage, 'portal-ordinance.png');
    await portal.close();

    fs.writeFileSync(
        shot('session-ids.json'),
        JSON.stringify(
            {
                base: BASE,
                liveSessionId: live.id,
                liveTitle: '38th Regular Session',
                pastSessionId: past.id,
                pastTitle: '37th Regular Session',
                minutesId,
                minutesUrl,
            },
            null,
            2,
        ),
    );

    await secretariat.context.close();
    await member.context.close();
    await hall.context.close();
    await transcript.context.close();
    await pastPage.context.close();
    await compare.context.close();
    await audit.context.close();
} finally {
    await browser.close();
    fs.writeFileSync(
        shot('capture-report.json'),
        JSON.stringify({ needs, notes }, null, 2),
    );
    if (needs.length) {
        console.log('NEEDS DEMO CONTENT:');
        for (const line of needs) {
            console.log(`- ${line}`);
        }
    }
}
