# Share Image Facts, not sizing algorithms

The content, `image_downsize` and srcset paths each decide Tachyon arguments with their own algorithm. They look alike, for example `crop ? 'resize' : 'fit'` appears in several places, but each one carries its own Plugin Quirks, which ADR 0001 says must be kept. We keep the three algorithms separate. What they share is Image Facts: one module behind a seam, with a WordPress adapter in production and an in-memory adapter in unit tests. A single sizing module would need a switch for every caller to keep those quirks. Its interface would then be as large as its implementation, and it would still be as hard to test.

## Consequences

- Looking up an attachment's source for a size is an Image Fact, even though WordPress answers it by running our own `image_downsize` filter. Unit tests stub it. The differential harness covers the real chain.
- The `tachyon_*` filters stay in the sizing paths, not behind the Image Facts seam. They are Public API.
- URL generation is not an Image Fact. The sizing paths call `Url::build()` directly instead of the global `tachyon_url()`. That function is only a wrapper, but a test stub for it would leave the function defined and make Stand-down think another Tachyon plugin had loaded.
