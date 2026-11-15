// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

// Synthetic/structural check only: no actual participant, model or research labels.
import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { readFileSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { join } from 'node:path';
import { test } from 'node:test';

const root = fileURLToPath(new URL('../../', import.meta.url));
const prompt = readFileSync(join(root, 'docs/research/prompts/human-one-paste-v0.2.txt'), 'utf8');
const script = readFileSync(join(root, 'docs/research/prompts/human-conversational-protocol-v0.2.md'), 'utf8');
const fixtureText = readFileSync(join(root, 'tests/Fixtures/research/conversational-review-v0.1.json'), 'utf8');
const fixture = JSON.parse(fixtureText);
const sha256 = value => createHash('sha256').update(value).digest('hex');
const extract = (start, end) => {
  const beginning = prompt.lastIndexOf('\n' + start + '\n');
  const ending = prompt.lastIndexOf('\n' + end + '\n');
  assert.ok(beginning >= 0 && ending > beginning, 'segment markers must exist in order');
  return prompt.slice(beginning + start.length + 2, ending).trim();
};

test('the one-paste artifact matches versioned protocol and public synthetic cases', () => {
  const result = spawnSync(process.execPath, ['scripts/research/build-conversational-pilot.mjs', '--check'], {cwd: root, encoding: 'utf8'});
  assert.equal(result.status, 0, result.stderr || result.stdout);
  assert.equal(extract('PROTOCOLO_INICIO', 'PROTOCOLO_FIM'), script.trim());
  assert.deepEqual(JSON.parse(extract('PACOTE_SINTETICO_INICIO', 'PACOTE_SINTETICO_FIM')), fixture);
  assert.ok(prompt.includes('PACOTE_SHA256: ' + sha256(fixtureText)));
  assert.ok(result.stdout.includes('prompt_sha256=' + sha256(prompt)));
});

test('case identity, provenance and neutral-state controls are bounded', () => {
  assert.equal(fixture.cases.length, 11);
  assert.equal(new Set(fixture.cases.map(c => c.caseId)).size, 11);
  assert.equal(fixture.cases.filter(c => c.reviewable).length, 10);
  assert.equal(fixture.cases.filter(c => !c.reviewable).length, 1);
  for (const c of fixture.cases) {
    assert.match(c.caseId, /^SYN-\d{2}$/);
    assert.match(c.sourceUrl, /\.example\.invalid\/$/);
    assert.ok(!('humanState' in c));
    assert.ok(!('automatedState' in c));
  }
  for (const state of ['present', 'absent', 'unknown', 'unavailable']) {
    assert.ok(script.includes('`' + state + '`'));
  }
  assert.match(script, /não classifica/);
  assert.match(script, /não infira código/);
  assert.match(script, /nunca invente duração/);
  assert.match(script, /20 minutos/);
  assert.match(script, /dez casos revisáveis/);
  assert.match(script, /não.*entrada direta de.*review:import/);
});

test('the participant entrypoint resolves the public project and private-data guardrail', () => {
  const start = readFileSync(join(root, 'docs/research/prompts/start-h0-v0.2.md'), 'utf8');
  assert.match(start, /https:\/\/github\.com\/vitormattos\/privacy-evidence/);
  assert.match(start, /https:\/\/raw\.githubusercontent\.com\/vitormattos\/privacy-evidence\/main\/docs\/research\/prompts\/human-one-paste-v0\.2\.txt/);
  assert.match(start, /não.*invent.*caso/);
  assert.match(start, /restritos/);
  assert.match(prompt, /inteiramente fictício/);
  assert.match(prompt, /não conclui o piloto oficial/);
});
