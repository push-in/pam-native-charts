# Changelog

## 0.2.0 - 2026-10-06

- The package is now a plain Composer library: PAM Native plugins may only depend on
  the core SDK, and charts build on the `pam-native-canvas` plugin. Register the
  tags with `Charts::register()` in `index.php`.


## 0.1.1 - 2026-10-06

- Accept colors as packed `0xAARRGGBB` integers (the form template attributes and
  bound arrays arrive in) for series, slices, `color`, `track` and theme values.


## 0.1.0 - 2026-10-05

- Initial public release.
- Add `LineChart`, `AreaChart`, `BarChart`, `DonutChart`, `Sparkline` and
  `ProgressRing`, immutable fluent builders that paint dp display lists on
  `pushinbr/pam-native-canvas` 0.2.
- Add `Series`, `Slice`, `ChartTheme`, `ChartPalette` value objects and the
  sequential integer enums `Curve`, `LegendPosition` and `BarLayout`.
- Add nice 1-2-5 value scales, monotone (Fritsch–Carlson) curves, gradient
  areas, rounded baseline-anchored bars, gapped donut slices, highlight
  crosshairs and pointer selection (`onSelect`).
- Register the declarative `<LineChart>`, `<AreaChart>`, `<BarChart>`,
  `<DonutChart>`, `<Sparkline>` and `<ProgressRing>` template tags with the
  `@select` event.
