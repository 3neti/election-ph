# Philippine Election Profile

The `profiles/2025-research.yaml` profile defines a research-only sequence: begin review, repeatable case decisions, and close review. It has no voting or canvassing transitions. AES supplies the case evidence and reviewer identity; `election-ceremonies` enforces the signed run binding and audit sequence.

`PhilippineSourceSnapshotBuilder` writes a read-only research registry of POP precincts and CLC source references. It deliberately contains no candidates or ballot styles. AES uses this builder for its signed 2025 source snapshot; the existing `PhilippineRegistryBuilder` continues to serve the pilot sample.

`3neti/election-ph` is a Philippine driver for `3neti/election-ceremonies`. `PhilippineRegistryBuilder` converts normalized election data to a transportable SQLite registry with elections, precincts, ballot styles, contests, candidates, associations, and style-scoped `CAND00001` codes. SQLite foreign keys check references during construction. The registry is then placed in a signed election bundle by the core package.

After commissioning, `PhilippineRegistryReader` opens the SQLite file read-only and returns the candidate code map for a precinct. The application can use this map to interpret ballot and return QR records against the same signed registry on every device.

`profiles/aes-pilot.yaml` models AES's thirteen precinct stages. It is a pilot profile: most transitions have no production evidence pipes yet. The election return transition runs EML export before Truth QR projection and requires both evidence items. The host must register those pipes and enforce officer and device policy. The signed bundle also carries the Truth QR profile so every device uses the same mapping.

AES currently supplies a one-precinct sample registry for end-to-end pilot tests. The available POP workbook and CLC files are not a reconciled nationwide registry, so this package cannot produce a `national` coverage claim from them. A production importer must validate complete POP/LOC coverage and stable codes before authority signing.

In AES, build the pilot with `php artisan election:bundle:build-pilot /absolute/path/pilot.zip --signing-key=/absolute/path/authority.key`. The key file must contain a raw Ed25519 secret key and should be held on the offline authority machine. AES refuses to overwrite an existing bundle.
