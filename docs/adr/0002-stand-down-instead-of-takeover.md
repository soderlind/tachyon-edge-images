# Stand down instead of taking over from the Reference Plugin

The Reference Plugin declares `tachyon_url()` and `class Tachyon` without guards, so the site fatals if both plugins load in the same request. Tachyon Edge Images therefore enters Stand-down whenever the Reference Plugin is active or already loaded. It shows an admin notice with a one-click deactivate button and never deactivates the Reference Plugin by itself. Taking over silently would surprise admins: the Deviations change output, and WordPress.org guidelines frown on plugins that change other plugins' state. Blocking activation would make the migration harder without adding any safety.

## Consequences

- The main plugin file must not declare any Public API symbol. Those are loaded from a separate file only when no Stand-down applies.
- If the Reference Plugin is an mu-plugin or loaded through Composer, it can't be deactivated from the admin. The notice explains how to remove it instead.
- The reverse order can't be guarded: activating the Reference Plugin while Tachyon Edge Images is running redeclares `tachyon_url()`. WordPress's activation sandbox catches the fatal and refuses the activation, so the site stays up. To switch back, deactivate Tachyon Edge Images first.
