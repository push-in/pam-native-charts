# Changelog

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
