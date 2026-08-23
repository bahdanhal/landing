# Community Market Price Tips

The used-price index is a manually curated editorial series. It does not use an automated marketplace crawler or an AI model to create price observations.

## Submission purpose

Visitors may voluntarily submit a public listing URL for one tracked product. Bahdan uses the link only to review whether a published aggregate asking-price observation should be updated. A submission never updates a public price automatically.

## Data handling

- Accept only absolute public HTTP or HTTPS URLs without embedded credentials.
- Remove query strings and fragments before persistence to avoid retaining tracking identifiers or access tokens.
- Never fetch a submitted listing automatically.
- Never store listing text, seller names, seller profiles, photographs, or other scraped content.
- Keep the normalized URL, product slug, optional submitter email, HMAC-hashed IP address, submission time, and expiry time in private storage.
- Show submissions only in the authenticated administration interface.
- Never place submitted URLs or contact details in application logs or public pages.
- Delete each individual submission automatically after 90 days.

## Abuse controls

The endpoint requires a same-origin browser submission, includes a honeypot field, validates all input, and accepts at most five submissions per IP-derived rate-limit key per UTC day. The application stores only an HMAC-derived IP hash with the tip.

## Privacy basis and review

The form explains its purpose and retention before submission. This design follows the GDPR principles of purpose limitation, data minimization, storage limitation, and confidentiality. The intended basis for this narrow private review workflow is the controller's legitimate interest in maintaining an accurate community-assisted price guide, subject to the visitor's reasonable expectations and right to object.

This document describes the implemented technical policy; it is not a substitute for individualized legal advice or a complete public privacy notice. Review the workflow whenever its purpose, fields, recipients, or retention period changes.
