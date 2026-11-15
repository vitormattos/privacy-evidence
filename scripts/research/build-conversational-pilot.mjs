// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

// Rebuilds the public, fully synthetic one-paste H0 pilot prompt from versioned sources.
// Never use this generator to publish restricted PoC evidence.
import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { readFile, writeFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { join } from 'node:path';

const root = fileURLToPath(new URL('../../', import.meta.url));
const fixtureFile = join(root, 'tests/Fixtures/research/conversational-review-v0.1.json');
const scriptFile = join(root, 'docs/research/prompts/human-conversational-protocol-v0.2.md');
const outputFile = join(root, 'docs/research/prompts/human-one-paste-v0.2.txt');
const [fixtureText, scriptText] = await Promise.all([
  readFile(fixtureFile, 'utf8'), readFile(scriptFile, 'utf8'),
]);
const fixture = JSON.parse(fixtureText);
assert.equal(fixture.sourceKind, 'fully_synthetic_public_fixture');
assert.equal(fixture.cases.length, 11);
assert.equal(fixture.reviewableCaseCount, 10);
assert.equal(fixture.deferredCaseCount, 1);
assert.equal(new Set(fixture.cases.map(c => c.caseId)).size, fixture.cases.length);
assert.equal(fixture.cases.filter(c => c.reviewable).length, 10);
assert.equal(fixture.cases.filter(c => !c.reviewable).length, 1);
for (const c of fixture.cases) {
  assert.match(c.caseId, /^SYN-\d{2}$/);
  assert.match(c.sourceUrl, /^https:\/\/case-\d+\.example\.invalid\/$/);
  assert.ok(!('automatedState' in c) && !('humanState' in c));
  assert.equal(typeof c.preservedMaterial === 'string' && c.preservedMaterial.length > 0, c.reviewable);
  assert.equal(c.reviewable ? c.deferredReason : c.deferredReason !== null, c.reviewable ? null : true);
}
assert.match(scriptText, /https:\/\/github\.com\/vitormattos\/privacy-evidence\/issues\/195/);
assert.match(scriptText, /não classifica/);
assert.match(scriptText, /20 minutos/);

const sha = text => createHash('sha256').update(text).digest('hex');
const packetSha = sha(fixtureText);
const normalizedScript = scriptText.trimEnd();
const content = [
  'PRIVACY EVIDENCE — PILOTO CONVERSACIONAL H0 (0.2.0, AMOSTRA FICTICIA)',
  '',
  'PARA PARTICIPAR: copie TODO este arquivo, cole em uma conversa nova com sua IA e responda ao que ela perguntar.',
  'Nao precisa procurar o projeto, baixar arquivos adicionais nem visitar os sites ficticios.',
  'LINK DO PROJETO: https://github.com/vitormattos/privacy-evidence',
  'ISSUE DO PILOTO REAL: https://github.com/vitormattos/privacy-evidence/issues/195',
  'ORIGEM DA AMOSTRA: https://github.com/vitormattos/privacy-evidence/blob/main/tests/Fixtures/research/conversational-review-v0.1.json',
  'REGRAS DA PESQUISA: https://github.com/vitormattos/privacy-evidence/blob/main/docs/research/reviewer-pilot-protocol.md',
  'TIPO: ensaio H0 sintetico; nao constitui a homologacao humana oficial da issue #195.',
  'PACOTE_SHA256: ' + packetSha,
  '',
  'PROTOCOLO_INICIO',
  normalizedScript,
  'PROTOCOLO_FIM',
  '',
  'PACOTE_SINTETICO_INICIO',
  fixtureText.trimEnd(),
  'PACOTE_SINTETICO_FIM',
  '',
  'A IA DEVE COMEÇAR COM A PERGUNTA DE ELEGIBILIDADE, SEM ANTECIPAR ROTULOS OU DADOS.',
  '',
].join('\n');

if (process.argv.includes('--check')) {
  const actual = await readFile(outputFile, 'utf8');
  if (actual !== content) {
    throw new Error('One-paste prompt is stale. Run node scripts/research/build-conversational-pilot.mjs');
  }
  console.log('Prompt synchronized. prompt_sha256=' + sha(content) + ' packet_sha256=' + packetSha);
} else {
  await writeFile(outputFile, content, 'utf8');
  console.log('Generated ' + outputFile + ' prompt_sha256=' + sha(content) + ' packet_sha256=' + packetSha);
}
