# LGPD public-evidence profile

Profile version: **1.1.0**

## Scope

This profile maps generic Privacy Evidence observations to LGPD-related public-transparency dimensions. It does not certify legal compliance and does not infer organization-wide practices from a website alone.

## Authoritative basis

- Lei nº 13.709/2018 (LGPD), especially arts. 9, 18, 33–36 and 41:
  https://www.planalto.gov.br/ccivil_03/_ato2015-2018/2018/lei/l13709.htm
- ANPD materials and guidance:
  https://www.gov.br/anpd/pt-br/centrais-de-conteudo/materiais-educativos-e-publicacoes
- Resolução CD/ANPD nº 19/2024, for transparency about international transfers:
  https://www.gov.br/anpd/pt-br/acesso-a-informacao/institucional/atos-normativos/regulamentacoes_anpd/resolucao-cd-anpd-no-19-de-23-de-agosto-de-2024

## Observable dimensions

The profile evaluates these dimensions independently:

- purpose of processing;
- duration/retention-related information;
- controller identity and public privacy contact;
- recipient/shared-use disclosure;
- data-subject-rights information;
- public channel for exercising rights;
- encarregado role/contact evidence, with applicability left unresolved when it cannot be established externally;
- international-transfer transparency, only as a conditional dimension because a website cannot establish whether transfers occur.

## State semantics

- `observed_support`: all evidence types mapped to the requirement were observed;
- `partial_observed_support`: some mapped evidence was observed;
- `no_observed_support`: the resource was measurable but the mapped non-conditional signal was observed as absent;
- `indeterminate`: measurement produced unresolved evidence;
- `unavailable`: required evidence could not be measured;
- `not_applicable`: the measurement explicitly established non-applicability;
- `applicability_unknown`: the requirement is conditional and the public measurement cannot establish whether it applies.

Missing or inaccessible website evidence is never converted into a legal non-compliance verdict.

## Interpretation boundary

The output is a structured description of **publicly observable LGPD-related evidence**. Legal compliance depends on facts, processing operations, governance, contracts, security measures and other conditions that are not fully observable from a public website.
