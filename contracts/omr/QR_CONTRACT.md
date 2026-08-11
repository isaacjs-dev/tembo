# Tembo OMR QR contract

The normative structural schema is `qr-payload.schema.json`. Cross-runtime test
vectors are in `qr-contract.vectors.json`. The QR wire version (`v`) is never a
template version (`tpl_v`).

## Compatibility matrix

| Wire | Read | Server authentication | Offline capture | Emitted now |
| --- | --- | --- | --- | --- |
| v3 | historical | required | no: no signed page geometry | no |
| early v4 | historical | required | no: page range/option counts absent | no |
| full v4 | yes | required | yes; official grading is deferred | no |
| v5 | yes | required | yes; official grading is deferred | yes |
| unknown | rejected | N/A | no | no |

No v6 is defined. A new wire version is required only when a change cannot be
represented by optional fields without changing existing semantics.

## Fields

- `e`: Assessment identifier.
- `c`: immutable printed-copy identifier.
- `h`: opaque copy validation token; it indirectly pins the copy's Assessment revision.
- `p` / `pt`: one-based page and total pages.
- `qs` / `qe`: first and last printed question on this page.
- `v`: QR wire version.
- `rpp`: rows per column on the printed page.
- `tpl_id` / `tpl_v`: exact OMR template and immutable version.
- `g`: six integers, scaled by 10,000, describing page-local geometry.
- `oc`: one digit per printed question: `0` essay, `2` true/false, `2..9` objective options.
- `gab_enc`: accepted historical AES-256-GCM ciphertext. Only the server decrypts
  it; current cards omit it because the immutable copy snapshot is authoritative.
- `chk`: tenant-derived HMAC-SHA-256 authenticator. Current v5 emission carries a
  128-bit tag encoded as 22 base64url characters. Historical accepted encodings
  remain in the schema.

`cols` and `tpl` are historical v4-compatible fields. `pts` remains accepted for
already printed cards but is no longer emitted because the immutable copy snapshot
is authoritative for points.

## Trust boundary

Web and Mobile validate structure before image processing, but they do not receive
the tenant HMAC/decryption key. They preserve the exact signed object. The server:

1. derives authorization from the authenticated user/workspace, never from QR IDs;
2. verifies the version allowlist and HMAC with the authenticated tenant;
3. binds Assessment, copy, opaque token, template/version and page contract to the
   immutable `ExamCopy` snapshot;
4. rejects answers outside `qs..qe`;
5. performs authoritative grading and records human review separately.

The allowlist contains no name, registration number, CPF, e-mail or other PII.
Plaintext `gab` is forbidden in every supported version.

Current v5 emission deliberately carries identity, page geometry and option counts,
but not `gab_enc`. Omitting this optional historical field reduces QR density while
keeping server-side grading bound to the immutable copy snapshot.

## Cryptographic and schema references

- JSON Schema Draft 2020-12: https://json-schema.org/draft/2020-12
- NIST SP 800-107 Rev. 1 (truncated HMAC): https://csrc.nist.gov/pubs/sp/800/107/r1/final
- OWASP Cryptographic Storage Cheat Sheet (authenticated encryption/GCM):
  https://cheatsheetseries.owasp.org/cheatsheets/Cryptographic_Storage_Cheat_Sheet.html

The QR-002 physical profile emits 30 mm black-on-white symbols with error correction
M, a four-module quiet zone and at least 0.35 mm per module. Payloads that exceed that
area fail before printing. Automated production-view PDF raster checks cover 150,
200 and 300 dpi plus a 300-pixel capture envelope; real printer/device approval
remains an explicit human validation.
