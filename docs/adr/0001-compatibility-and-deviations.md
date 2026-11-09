# Compatible with the Reference Plugin, with Deviations on by default

Tachyon Edge Images reproduces the Reference Plugin's Public API and generated URLs, including Plugin Quirks. The exceptions are documented Deviations, which are always on. A Deviation only fixes output that no working site can depend on: malformed URLs, PHP warnings, the wrong image size, or resized images in the block editor. We chose this because the fixes found while patching tachyon-plugin (humanmade/tachyon-plugin#88 and the block-editor fix) all change output. Reproducing those bugs would also bring back the PHP warnings, and making the fixes opt-in would mean most sites never got them.

## Consequences

- Every Deviation is listed in the docs and tagged in the differential test against the Reference Plugin. Any other difference counts as a bug.
- A new behaviour that changes output a working site could depend on is not a Deviation. It has to be opt-in.
