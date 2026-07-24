# Changelog

All notable changes to `laboiteacode/filament-business-hours` will be documented in this file.

## v1.0.1 - 2026-07-24

Supply-chain hardening release. No code changes.

- CI: GitHub Actions are now pinned to full commit SHAs
  (actions/checkout v4.4.0, shivammathur/setup-php 2.37.2), so a
  re-pointed tag can no longer inject code into the build.
- Security policy: new SECURITY.md describing the private vulnerability
  reporting channels (GitHub advisories or email) and the response
  timelines.
- Dependencies: Dependabot now watches Composer packages and GitHub
  Actions weekly, with a 7-day cooldown (14 days for majors) so a
  compromised release is never pulled before the ecosystem reacts.
- Dist archive: SECURITY.md is excluded from the Composer distribution
  like the other repository-only files.

## v1.0.0 - 2026-07-23

First stable release. The plugin API and the stored format (native
spatie/opening-hours definitions) are now considered stable.

- Documentation: a full "Querying the hours with spatie/opening-hours"
  guide (one-liner, spatie query examples, model-free raw access, caching,
  seeding from code).
- License: MIT copyright notice updated (Alexandre Ribes, La Boîte à Code).
- Removed the development warning from the README.

## v0.2.0 - 2026-07-22

Hardening and quality release.

- Security hardening of the client-writable Livewire state: hard caps
  (100 ranges per day, 500 exception and vacation entries, 1100 days per
  vacation period, 255 characters per label), server-side timezone
  validation, explicit mass assignment whitelist on the model, input
  validation in the ExceptionEntry factories, and defensive rendering so a
  malformed state can no longer break the page.
- Alignment with spatie/opening-hours: February 29th is rejected as a yearly
  recurring date with a readable message instead of a cryptic one.
- Code review fixes: the exception and vacation edit modals no longer crash
  on malformed state, the grid's inline editor guards against detached
  layout containers, clicks at the very bottom of a column now create a
  range, and per-day totals are translatable.
- Accessibility: a keyboard-reachable "add a range" button in each day
  header creates a range on the first free hour and opens the precise
  editor, so ranges can be created without a pointer.
- Test suite extended from 55 to 112 tests (370 assertions), including a
  dedicated crafted-payload suite and full plugin configuration coverage.
- Marketing banners in `art/`, banner and a "Validation and limits" section
  in the README, updated credits.

## v0.1.0 - 2026-07-22

Initial development release.

- Weekly planner grid (7 days of hour cells, Filament palette): drag to
  create ranges, move, resize, precise inline editor on click or right-click,
  minute badges on partial cells, copy and clear days, configurable first day
  of week and snapping step.
- Exceptional dates (closed or special hours, optionally yearly recurring)
  and vacation periods, with labels.
- Timezone picker and spatie "overflow" flag.
- Storage in the native spatie/opening-hours definition format, validated
  against the library itself on every save.
- Config file and fluent per-panel plugin API, authorization hook, English,
  French and Spanish translations.
