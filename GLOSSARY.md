# Tachyon Edge Images

Tachyon Edge Images is a WordPress plugin that rewrites image URLs to a Tachyon Server. It replaces the Reference Plugin without any configuration changes and fixes the Reference Plugin's bugs as Deviations.

## Language

**Reference Plugin**:
Human Made's tachyon-plugin 0.11.1, the upstream release. Tachyon Edge Images is measured against it.
_Avoid_: original plugin, upstream, the fork

**Public API**:
The constants, global functions, class, public methods and `tachyon_*` hooks of the Reference Plugin that themes and other plugins rely on.
_Avoid_: interface, surface

**Compatible**:
Has the same Public API as the Reference Plugin and generates the same URLs for the same input, except where a Deviation applies.
_Avoid_: drop-in, parity

**Deviation**:
A documented difference from the Reference Plugin. It fixes output that no working site can depend on, such as a malformed URL, a PHP warning, the wrong image size, or the editor showing resized images.

**Plugin Quirk**:
Odd behaviour of the Reference Plugin that is kept on purpose and documented, because sites may depend on it.
_Avoid_: bug, Release Quirk (that term belongs to Tachyon Edge)

**Tachyon Server**:
The image service that `TACHYON_URL` points to: Reference Tachyon or Tachyon Edge.
_Avoid_: backend, CDN, Origin

**Stand-down**:
The state in which Tachyon Edge Images does nothing because the Reference Plugin is active. The admin is told and can deactivate the Reference Plugin.
_Avoid_: takeover, conflict mode

**Edge-only Feature**:
An opt-in feature that only works when the Tachyon Server is Tachyon Edge.
