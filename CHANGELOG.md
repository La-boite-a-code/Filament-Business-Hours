# Changelog

All notable changes to `laboiteacode/filament-business-hours` will be documented in this file.

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
