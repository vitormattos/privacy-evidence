# Focused review: privacy/web measurement tools and methods

Review version: **0.1.0**
Evidence cutoff: **2026-10-07**

## Purpose

This review establishes the novelty boundary for Privacy Evidence. It is not a marketing comparison and it does not assume that undocumented capabilities are absent.

The review asks:

1. Which existing tools already collect or analyze web privacy evidence?
2. Which parts of the Privacy Evidence workflow overlap with those tools?
3. Which candidate differentiators remain plausible?
4. Which differences require an empirical baseline experiment rather than documentation review?

## Inclusion criteria

Include a tool/project when at least one of the following is true:
- it is used or designed for empirical web privacy measurement;
- it collects browser/network/cookie/tracker evidence for privacy analysis;
- it supports batch/population-scale website analysis;
- it is a commercially relevant privacy scanner/CMP whose documented capabilities overlap with the project.

Prefer primary sources:
- official project/repository documentation;
- authoritative institutional documentation;
- peer-reviewed publication describing the instrument;
- vendor documentation for commercial capabilities.

## Exclusion / interpretation rules

- A missing documentation statement is **unknown**, not documented absence.
- Vendor legal/compliance claims are product claims, not independent scientific validation.
- Tool scale, feature breadth and measurement validity are distinct questions.
- “Reproducible” is recorded only when the source itself makes a reproducibility/versioning claim or exposes enough mechanism to evaluate it.
- No “first”, “unique”, “best” or “state of the art” claim is permitted from this review alone.

---

## 1. EDPS Website Evidence Collector (WEC) / WEC Online

Primary source:
- https://www.edps.europa.eu/data-protection/technology-monitoring/data-protection-and-privacy-tools_en

The European Data Protection Supervisor describes WEC and WEC Online as open-source inspection tools for gathering evidence on personal-data processing by websites using a reproducible, reliable and fast method.

Documented WEC capabilities include:
- Chromium with a fresh browser profile;
- screenshots;
- page/link collection;
- HTML5 local storage;
- cookies/similar technologies;
- third-party requests / transferred information;
- human- and machine-readable evidence outputs;
- preconfigured collection parameters;
- standalone CLI and a web-based WEC Online interface.

### Implication for Privacy Evidence

Privacy Evidence must **not** claim novelty for:
- browser-based privacy evidence collection;
- collection of cookies/third-party requests;
- open-source website privacy inspection;
- reproducible website evidence collection as a generic idea.

WEC is the strongest external baseline for a controlled comparison because it overlaps directly with the acquisition/evidence domain while having an authoritative institutional origin.

### Unresolved comparison questions

Primary documentation reviewed here does not establish that WEC provides the same end-to-end semantics as Privacy Evidence for:
- source-population accounting before crawl;
- canonical source-to-resource transformation and deduplication provenance;
- explicit research states for unknown/unavailable/invalid/excluded across the full intended population;
- independently blinded human annotation and agreement-before-adjudication;
- detector-performance evaluation against a project-owned human reference;
- versioned generic-evidence-to-multiple-regulatory-profile mapping;
- a ResearchRun construct binding dataset/code/protocol/profile/reviewer configuration.

Those are **not yet novelty claims**. They are comparison hypotheses for #190/#191.

---

## 2. OpenWPM

Primary sources:
- https://github.com/openwpm/OpenWPM
- https://openwpm.readthedocs.io/en/stable/Platform-Architecture.html

OpenWPM describes itself as a web privacy measurement framework supporting studies from thousands to millions of websites.

Documented characteristics include:
- Firefox/Selenium-based automation;
- browser instrumentation for HTTP traffic, JavaScript APIs, cookies, navigation and related telemetry;
- TaskManager-controlled multiple browser instances;
- timeouts/restart behavior for browser failures;
- structured/unstructured storage providers;
- command/status logging that contributes to experiment reproducibility;
- versioned releases and guidance to report the OpenWPM version in publications;
- Docker support and pinned dependencies in modern releases.

### Implication for Privacy Evidence

Privacy Evidence must **not** claim novelty for:
- large-scale automated privacy measurement;
- browser-process orchestration;
- structured privacy telemetry collection;
- failure recovery/watchdogs in browser measurement;
- research-oriented versioning and storage as generic ideas.

OpenWPM is primarily a measurement framework/instrumentation platform. The current review does not establish that it supplies Privacy Evidence's domain-specific source-population accounting, evidence taxonomy, regulatory profiles or human-reference evaluation workflow.

---

## 3. Blacklight / Blacklight Query

Primary sources:
- https://themarkup.org/blacklight
- https://themarkup.org/blacklight/2020/09/22/how-we-built-a-real-time-privacy-inspector
- https://themarkup.org/blacklight/2024/10/16/blacklight-query

Blacklight is a real-time website privacy inspector. Its documented implementation uses a headless browser and tests for specific tracking/surveillance behaviors.

Blacklight Query extends the same collector to batch/list-based CLI operation and produces a folder of scan results for multiple URLs.

Documented scope includes detection of tracking technologies such as cookies and advertising/tracking pixels and other browser-observable tracking behavior.

### Implication for Privacy Evidence

Blacklight eliminates any novelty claim based solely on:
- a user submitting a URL and receiving a privacy-oriented result;
- headless-browser tracking inspection;
- batch scanning of URL lists.

Blacklight's published description is more focused on tracking/surveillance technologies than on privacy-policy/document evidence, multi-layer regulatory mapping or independently reviewed detector validation.

---

## 4. PrivacyScore

Primary source:
- https://github.com/PrivacyScore/PrivacyScore

PrivacyScore describes itself as a web privacy measurement platform for investigating security/privacy issues on websites.

Documented goals/features include:
- comparing and ranking lists of sites;
- checking known third-party trackers;
- periodically rescanning sites;
- checking how results change over time;
- open-source extensibility.

### Implication for Privacy Evidence

Privacy Evidence must not claim novelty merely for:
- list/population comparisons;
- periodic rescanning;
- longitudinal comparison as a general feature;
- open-source privacy measurement dashboards/platforms.

PrivacyScore strengthens the need to treat #113 as a future empirical contribution requiring a clearly differentiated research question.

---

## 5. Webbkoll

Primary source:
- https://github.com/andersju/webbkoll
- current upstream is indicated by the archived repository as https://codeberg.org/dataskydd.net/webbkoll

Webbkoll is described as an online tool that checks how a website performs with regard to privacy.

The reviewed GitHub repository is archived and points to a Codeberg continuation.

### Implication for Privacy Evidence

Webbkoll is prior art for a public-facing single-site privacy check.

The current review does not establish publication-grade provenance, population accounting or independent human-reference evaluation in Webbkoll. Those remain unknown until stronger primary documentation is reviewed.

---

## 6. GDPR Observer

Primary source:
- https://github.com/hermescenter/gdpr.observer

GDPR Observer is especially relevant because it extends EDPS WEC for population/collection workflows.

Its README documents:
- lists/collections of websites as input;
- automatic and continuous collection/analysis;
- searchable compliance-check results grouped by collections/country;
- APIs/open data;
- WEC as the main evidence collector;
- a multilingual consent-acceptance clicker;
- repeated batch testing;
- collection metadata and curation workflows.

### Implication for Privacy Evidence

This is important prior art against claims that Privacy Evidence is the first system to:
- combine WEC-like evidence with lists/collections;
- run repeated population-level privacy checks;
- expose results through a service/API;
- add analysis on top of WEC outputs.

However, GDPR Observer's documented framing uses “GDPR Compliance Checks”. Privacy Evidence deliberately separates observable evidence, regulatory mapping and legal-compliance conclusions. Whether that separation is a defensible methodological differentiator must be evaluated rather than asserted.

---

# Commercial landscape

Commercial systems are included to characterize product overlap, not scientific novelty.

## OneTrust

Primary sources:
- https://www.onetrust.com/products/cookie-consent/
- https://my.onetrust.com/articles/en_US/Knowledge/UUID-49bd8301-a150-6107-7409-de3297816efa
- https://developer.onetrust.com/onetrust/reference/addscans

Documented capabilities include:
- website scanning for cookies, tags, trackers, pixels, beacons, forms/storage;
- Chrome-based scanner infrastructure;
- first-/third-party inventory;
- scheduled/repeated scanning;
- automated categorization;
- consent/CMP workflows;
- API-triggered scans.

This is relevant to the LibreCode service concept because it demonstrates an established paid market for continuous scanning, inventory and consent operations.

## Cookiebot / Usercentrics

Primary sources:
- https://www.cookiebot.com/
- https://support.cookiebot.com/hc/en-us/articles/360013475319-Why-choose-Cookiebot-CMP
- https://support.cookiebot.com/hc/en-us/articles/5007079527580-Understanding-the-scan-report

Documented capabilities include:
- automated recurring site scans;
- crawling of subpages;
- cookie/tracker detection and categorization;
- reports and tracking-change detection;
- consent-banner/blocking integration.

Cookiebot is direct prior art for “enter/use a website, scan cookies/trackers, receive compliance-oriented output” as a product concept.

## Privado AI Web Auditor

Primary source:
- https://www.privado.ai/products/web-auditor

Documented capabilities include:
- continuous website scanning;
- consent-banner testing;
- cookies, pixels, scripts and third-party activity;
- sensitive-data-flow/risk discovery;
- geography/regulation-oriented compliance checks;
- a free-audit lead-generation path.

This is relevant to the commercial/service landscape and to user expectations for a future hosted Privacy Evidence service.

---

# Preliminary overlap matrix

Legend:
- **D** = documented capability in reviewed primary source;
- **U** = unknown from reviewed source; not absence;
- **N/A** = not meaningful for that tool category.

| Capability | Privacy Evidence | WEC | OpenWPM | Blacklight Query | PrivacyScore | GDPR Observer | Commercial scanners |
|---|---|---|---|---|---|---|---|
| Single-site browser evidence | D | D | D | D | D | via WEC | D |
| Batch/list operation | D | U/depends wrapper | D | D | D | D | often D |
| Large-scale research instrumentation | D | U | D | batch-oriented | D | D | product-dependent |
| Cookies/third-party requests | D | D | D | D | D/trackers | D via WEC | D |
| Privacy-policy/document evidence | D | U | generic collection possible | not central in reviewed docs | U | U | product-dependent |
| Intended source-population accounting | D | U | U | U | list-oriented U | collection-oriented D/partial | U |
| Explicit unavailable/unknown state semantics | D | U | command/failure logging D/partial | U | U | U | U |
| Immutable evidence/artifact provenance | D | evidence outputs D/partial | logging/storage D/partial | result artifacts D/partial | U | raw data paths/ids D/partial | U |
| Independent blinded human review | D | U | U | U | U | curation D/partial | usually human workflow, U |
| Agreement-before-adjudication | D | U | U | U | U | U | U |
| Detector precision/recall/F1 vs human reference | D workflow; final evidence pending | U | study-specific, not framework default | U | U | U | U |
| Versioned regulatory profiles over generic evidence | D | U | N/A | N/A | U | compliance checks D/partial | D/product-specific |
| Run binds dataset+code+protocol+config versions | D | U | version/config logging D/partial | U | U | test/date identity D/partial | U |
| Periodic/longitudinal runs | implemented comparison | U | study-defined | repeatable | D | D | D |
| Public hosted scan concept | not yet; #184 | WEC Online D | no default public SaaS | D | D | D | D |

This table is deliberately conservative. “U” must not be rewritten as “No” without further evidence.

---

# Preliminary novelty boundary

## Claims already ruled out

Privacy Evidence is not novel merely because it:
- collects privacy evidence with a browser;
- collects cookies or third-party requests;
- runs scans reproducibly;
- analyzes lists of websites;
- supports repeated scans;
- offers or could offer a public URL scanner;
- is open source;
- produces machine-readable privacy scan results.

Existing tools document all of those ideas in different combinations.

## Candidate differentiators requiring #190/#191

The strongest remaining hypotheses are the **combination and research semantics** around:
- complete accounting from declared source population to analytical population;
- explicit preservation of measurement attrition rather than dropping failed resources;
- semantic distinction among absent, unknown, unavailable, invalid, excluded and not-applicable;
- preserved chain from artifact -> detector output -> independent human decision -> adjudication -> regulatory interpretation;
- detector validation as a measurement-instrument question;
- ResearchRun-level binding of dataset, code, protocol, schema, detector/profile/handbook versions and configuration;
- analysis of how alternative missingness treatments change empirical conclusions.

The review does **not** yet establish that no other system implements these features. #190 must audit each claim and #191 should experimentally compare WEC where documentation cannot answer the question.

---

# Research decisions

1. WEC is the primary external acquisition/evidence baseline for #191.
2. OpenWPM remains the primary scientific architecture reference for large-scale browser measurement.
3. GDPR Observer must be included in related work because it directly combines WEC with website collections and repeated analysis.
4. Blacklight Query and PrivacyScore prevent weak novelty claims around batch scanning and longitudinal/privacy-list analysis.
5. Commercial tools belong in product/technology-transfer context, not as substitutes for peer-reviewed related work.
6. Privacy Evidence's defensible contribution should be framed around measurement semantics, attrition/provenance and validated research workflow unless #190/#191 falsify that position.
