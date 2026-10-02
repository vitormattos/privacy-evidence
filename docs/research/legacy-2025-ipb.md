# 2025 IPB/TCC legacy baseline and compatibility mapping

## Historical artifact

The implementation used for the 2025 IPB study remains preserved at:

`vitormattos/webscraping-anuario-igrejas-ipb`

Privacy Evidence does not import that Git history or its monolithic `parse.php`. The legacy repository is provenance/reference material.

## Published baseline

The monograph reports:
- 2,935 IPB churches analyzed;
- 640 churches with a site/address informed;
- 532 addresses after excluding social networks;
- 219 valid/active addresses showing content;
- 22 sites with some LGPD mention;
- 16 with cookie notice;
- 15 with privacy policy;
- 12 with a generic e-mail/channel associated with DPO/contact;
- 5 with a specific data-subject-rights form;
- 2 with an identified DPO/encarregado.

These counts belong to the 2025 protocol and are immutable historical results.

## Legacy implementation semantics

The historical script:
- fetched the iCalvinus Anuário through a POST request;
- parsed churches and pastors from HTML;
- stored results in SQLite;
- nulled social-network website values during cleaning;
- represented website validation/classification with magic integer codes;
- overloaded `website_dados_lgpd` with provider/classification strings;
- disabled TLS verification during website checks;
- contained copy/paste vendor-classification conditions where several provider functions searched for `inradar`.

Those behaviors are **not** silently preserved as current protocol semantics.

## Compatibility map

| 2025 concept | Privacy Evidence | Compatibility |
|---|---|---|
| church row | entity/source metadata + ImportedResource | compatible with transformation |
| website_dado_original | ImportedResource.sourceValue | direct |
| website | normalizedUrl | lossy if legacy cleaning already removed source values; new pipeline preserves raw value |
| website_validado=1/2/... | structured DNS/TLS/HTTP/resource-type observations | not directly compatible |
| social URLs nulled | ResourceType::SocialNetwork | definition changed; original value retained |
| website_dados_lgpd | independent PrivacyEvidence items | incompatible overloaded field |
| LGPD mention | PrivacyLawReference evidence | approximately mappable if original evidence/source is available |
| privacy policy | PrivacyNotice evidence | approximately mappable; new detector/review protocol differs |
| cookie notice | CookieNotice evidence | approximately mappable; dynamic behavior is new |
| DPO/contact | separate PrivacyContact/DpoRole/DpoIdentity/DpoContact | legacy category must not be force-mapped |
| rights form | RightsChannel evidence | approximately mappable subject to original coding rule |

## Longitudinal rule

A comparison may show 2025 published counts alongside new runs, but it must:
1. identify the 2025 method as protocol-v1/legacy;
2. identify the current protocol/profile versions;
3. state denominator differences;
4. avoid presenting changed definitions as direct trend measurements;
5. preserve the published legacy value even when a new implementation would recalculate it differently.

## Reimplementation

The historical iCalvinus acquisition structure is represented in Privacy Evidence by `src/Source/Ipb/` with saved characterization fixtures. It preserves source values and removes the legacy database/magic-number/provider-classification coupling.
