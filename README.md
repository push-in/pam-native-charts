# PAM Native Charts

Native charts for PAM Native: line, area, bar, donut, sparkline and progress
ring, drawn as bounded display lists on
[`pushinbr/pam-native-canvas`](https://github.com/push-in/pam-native-canvas).
PHP describes the chart once; Android Canvas and Core Graphics paint it in dp
without calling PHP per frame.

## Start here

```bash
curl --proto '=https' --proto-redir '=https' --tlsv1.2 \
    --connect-timeout 15 --max-time 60 --max-filesize 1048576 -fsSL \
    https://github.com/push-in/pam/releases/latest/download/install.sh | sh
pam init my-app --template native
cd my-app
pam composer require pushinbr/pam-native-charts
pam doctor --fix
```

Platform support: PHP 8.5+, PAM Native 1.13.x, `pam-native-canvas` 0.2.x,
Android API 26+ and iOS 15+ (provided by the canvas plugin; this package is
PHP-only).

```php
use Pam\Native\Charts\Curve;
use Pam\Native\Charts\LineChart;
use Pam\Native\Charts\Series;

return LineChart::make(width: 328, height: 160)
    ->series(Series::make('Entradas', [120.5, 80, 140, 90, 200]))
    ->labels(['Seg', 'Ter', 'Qua', 'Qui', 'Sex'])
    ->curve(Curve::Monotone)
    ->area();
```

Every chart is an immutable, fluent builder and a `Renderable`: return it from
a screen or component, or place the tag in a template. `view()` exposes the
underlying `CanvasView` and `scene()` the raw display list for tests.

## Design rules

The charts follow one small visual system so they read as a family and stay
legible without color:

- **Thin marks.** Lines are 2 dp, bars have a 4 dp rounded far end anchored to
  the baseline with at least 2 dp between neighbours, markers are 8 dp, grid
  lines are 1 dp dashed at 12% ink.
- **One value axis.** Tick labels sit left of the plot; there is never a dual
  axis. Ticks use "nice" 1-2-5 steps and the domain always includes zero.
- **One hue per job.** Series take colors from a fixed-order categorical
  palette (or `ChartPalette::sequential()` for magnitudes). Status colors
  (success, warning, danger) are never used for series identity.
- **Readable without color.** `ChartTheme` carries `ink` and `muted` colors
  for labels; legends and `valueLabels()` carry series identity in text.
- **Everything in dp.** Charts receive an explicit `width`/`height` and emit a
  dp scene (`CanvasView::dp()`); pointer coordinates arrive in dp too.
- **Empty states.** Charts without values draw the baseline and a muted
  "Sem dados" label. Single points are drawn as markers.

## Themes and palettes

```php
use Pam\Native\Charts\ChartPalette;
use Pam\Native\Charts\ChartTheme;

$theme = ChartTheme::dark(
    primary: '#19c5ff',
    ink: '#f4f7fa',
    muted: '#a0adbb',
    grid: '#2c3947',     // omit for 12% ink
    surface: '#19222c',  // '#00000000' keeps the background transparent
);

$theme = ChartTheme::light();                    // light defaults
$theme = $theme->palette(ChartPalette::make('#19c5ff', '#a78bfa', '#f472b6'));
$theme = $theme->palette(ChartPalette::sequential('#19c5ff', steps: 5));

$chart = LineChart::make(328, 160)->theme($theme);
```

Colors accept `#rgb`, `#rrggbb` and `#rrggbbaa` (CSS order). Series and slices
without an explicit `color()` take the palette hue for their index, in fixed
order; the theme `primary` is always the first hue.

## Line chart

```php
use Pam\Native\Charts\{Curve, LegendPosition, LineChart, Series};

LineChart::make(width: 328, height: 160)
    ->theme($theme)
    ->series(Series::make('Entradas', [120.5, 80, 140, 90, 200])->color('#19c5ff'))
    ->series(Series::make('Saídas', [40, 60, 30, 80, 50])->color('#ff8990'))
    ->labels(['Seg', 'Ter', 'Qua', 'Qui', 'Sex'])          // x labels
    ->curve(Curve::Monotone)                                // Linear (default) or Monotone
    ->area(true)                                            // gradient under each line, 35% → 0
    ->grid(3)                                               // horizontal grid lines; 0 hides them
    ->axis(true)                                            // value tick labels
    ->legend(LegendPosition::Top)                           // None (default), Top, Bottom
    ->valueLabels(false)                                    // formatted value on every point
    ->valueFormatter(fn (float $v): string => 'R$ ' . number_format($v, 0, ',', '.'))
    ->highlight(2)                                          // crosshair + markers + values; null for none
    ->onSelect(function (?int $index): void { $this->selected = $index; });
```

`onSelect` receives the x index nearest to the pointer while it is down (and
`null` when the gesture is cancelled). Store it and re-render with
`highlight($index)`; the scene revision changes, so the native view redraws.
`indexAt(float $x)` resolves an index from a dp x coordinate when you need the
same logic elsewhere.

```xml
<LineChart
    :width="328" :height="160"
    :series="[
        ['label' => 'Entradas', 'values' => [120.5, 80, 140, 90, 200], 'color' => '#19c5ff'],
        ['label' => 'Saídas', 'values' => [40, 60, 30, 80, 50], 'color' => '#ff8990'],
    ]"
    :labels="['Seg', 'Ter', 'Qua', 'Qui', 'Sex']"
    curve="monotone"
    area="true"
    grid="3"
    legend="top"
    :highlight="$selected"
    :theme="['primary' => '#19c5ff', 'ink' => '#f4f7fa']"
    @select="pointSelected"
/>
```

Boolean and numeric props may arrive as strings (`area="true"`, `grid="3"`) and
are coerced. `:values="[...]"` is accepted as a shorthand for one unnamed
series. `@select` calls the handler with the selected index or `null`.

## Area chart

`AreaChart` is a `LineChart` whose area fill is on by default; every line chart
method applies.

```php
use Pam\Native\Charts\{AreaChart, Curve, Series};

AreaChart::make(width: 328, height: 120)
    ->series(Series::make('Saldo', [900, 1200, 1100, 1600, 1750]))
    ->labels(['Jan', 'Fev', 'Mar', 'Abr', 'Mai'])
    ->curve(Curve::Monotone);
```

```xml
<AreaChart :width="328" :height="120" :values="$balance" :labels="$months" curve="monotone" />
```

## Bar chart

```php
use Pam\Native\Charts\{BarChart, BarLayout, LegendPosition, Series};

BarChart::make(width: 328, height: 160)
    ->series(Series::make('Pix', [12, 18, 9, 22]))
    ->series(Series::make('Cartão', [8, 6, 11, 4]))
    ->labels(['Jan', 'Fev', 'Mar', 'Abr'])
    ->grouped()                      // default: one bar per series, side by side
    ->stacked()                      // or: series stacked on one bar per category
    ->layout(BarLayout::Stacked)     // the enum form of grouped()/stacked()
    ->barRadius(4)                   // rounded far end, in dp
    ->legend(LegendPosition::Bottom)
    ->valueFormatter(fn (float $v): string => (string) (int) $v)
    ->highlight(1)                   // tinted category slot + values above its bars
    ->onSelect(fn (?int $index) => ...);
```

Bars are anchored to the baseline; negative values grow downwards with the
rounding on the bottom. Stacked bars round only the outermost segment and the
value label shows the category total.

```xml
<BarChart
    :width="328" :height="160"
    :series="[['label' => 'Pix', 'values' => [12, 18, 9, 22]], ['label' => 'Cartão', 'values' => [8, 6, 11, 4]]]"
    :labels="['Jan', 'Fev', 'Mar', 'Abr']"
    stacked="true"
    barRadius="4"
    legend="bottom"
    :highlight="$selected"
    @select="monthSelected"
/>
```

## Donut chart

```php
use Pam\Native\Charts\{DonutChart, LegendPosition, Slice};

DonutChart::make(size: 160)
    ->slices([
        Slice::make('Pix', 70.0)->color('#19c5ff'),
        Slice::make('Cartão', 30.0),
    ])
    ->slice(Slice::make('Boleto', 12.5))        // appends one slice
    ->thickness(18)                              // ring thickness in dp
    ->gap(2)                                     // space between slices in dp
    ->center(title: 'R$ 1.234', subtitle: 'este mês')
    ->legend(LegendPosition::Bottom);            // rows below the ring; grows the height
```

Angles start at 12 o'clock and run clockwise. Slices with a zero value are
skipped; a donut without values draws the track and "Sem dados". With a legend
the chart height becomes `size + rows × 18 + 8`.

```xml
<DonutChart
    size="160"
    thickness="18"
    gap="2"
    :slices="[['label' => 'Pix', 'value' => 70, 'color' => '#19c5ff'], ['label' => 'Cartão', 'value' => 30]]"
    title="R$ 1.234"
    subtitle="este mês"
    legend="bottom"
/>
```

## Sparkline

```php
use Pam\Native\Charts\{Curve, Sparkline};

Sparkline::make(width: 96, height: 32)
    ->values([3, 5, 4, 8, 6])
    ->color('#19c5ff')        // defaults to the theme primary
    ->area(true)
    ->curve(Curve::Monotone)
    ->endMarker();            // dot on the last value
```

```xml
<Sparkline :width="96" :height="32" :values="[3, 5, 4, 8, 6]" color="#19c5ff" area="true" curve="monotone" endMarker="true" />
```

## Progress ring

```php
use Pam\Native\Charts\ProgressRing;

ProgressRing::make(size: 56)
    ->progress(0.42)          // 0..1, clamped
    ->thickness(6)
    ->color('#19c5ff')        // defaults to the theme primary
    ->track('#2c3947')        // defaults to the theme grid
    ->label('42%');           // centered, theme ink
```

```xml
<ProgressRing size="56" :progress="$ratio" thickness="6" color="#19c5ff" track="#2c3947" :label="$percent" />
```

## Composition and sizing

Charts set their own `width`/`height` on the native view. To compose the canvas
yourself, take `view()` (a `CanvasView` in dp units) or `scene()` and size the
element through your own layout:

```php
$chart = LineChart::make(328, 160)->series(Series::make('a', [1, 2, 3]));
$scene = $chart->scene();          // Pam\Native\Canvas\CanvasScene
$view = $chart->view();            // Pam\Native\Canvas\CanvasView, dp, pointer wired
$revision = $chart->revision();    // changes whenever the drawing changes
```

## API guide

| API | Responsibility |
| --- | --- |
| `LineChart` / `AreaChart` | Lines with optional gradient area, grid, legend, highlight and selection. |
| `BarChart` | Grouped or stacked rounded bars anchored to the baseline. |
| `DonutChart` | Gapped ring slices with a titled center and optional legend. |
| `Sparkline` | Tiny axis-less trend line. |
| `ProgressRing` | Round-capped progress arc with a center label. |
| `Series` / `Slice` | Immutable data inputs with finite-number validation. |
| `ChartTheme` / `ChartPalette` | Ink, grid and surface colors; fixed-order and sequential palettes. |
| `Curve` / `LegendPosition` / `BarLayout` | Sequential integer enums for every variant. |
| `ChartsPluginProvider` | Registers the template tags. |

All coded variants are sequential integer-backed enums. Use enum cases in
application code; do not depend on raw wire numbers.

## Production checklist

- Give every chart an explicit `width`/`height` in dp that matches its layout slot.
- Format values with `valueFormatter()` so ticks and labels use the product's locale.
- Keep status colors out of series; carry identity through labels and legends.
- Re-render with `highlight()` from `onSelect` instead of mutating the chart.
- Run `pam doctor`, `pam test`, and a signed release build on every supported platform.

## Troubleshooting

- **Nothing is drawn:** make sure `pushinbr/pam-native-canvas` 0.2+ is installed and `pam doctor --fix` regenerated the native integration.
- **Labels overlap:** widen the chart, shorten `labels()`, or lower `grid()`.
- **The highlight does not move:** store the index from `onSelect` in component state and pass it back through `highlight()`.
- **Template props ignored:** bind arrays with `:series="[...]"`; plain attributes are strings and only scalars are coerced.

## Examples

- [`examples/WeeklyMovement.pam`](examples/WeeklyMovement.pam): a finance card with a grouped
  bar chart (tap highlight), a sparkline and a donut, written as a `.pam` component.
- [`examples/builder.php`](examples/builder.php): the same charts from PHP builders, with a theme,
  a value formatter and a progress ring.

## Contributing

```bash
composer install
php tests/run.php            # scene snapshots, scales, curves, template registration
vendor/bin/phpstan analyse   # level max
```

Every chart type has a snapshot test on its display list; add one when a chart's marks change.
Drawing primitives belong in [`pam-native-canvas`](https://github.com/push-in/pam-native-canvas);
this library only composes scenes.

## Compatibility and support

This package targets PHP 8.5, PAM Native `1.13.x` and `pam-native-canvas` `0.2.x`.
Rendering requirements (Android API 26+, iOS 15+) come from the canvas plugin.

- [PAM documentation](https://push-in.github.io/pam-docs/introduction/)
- [PAM Native overview](https://push-in.github.io/pam-docs/native/overview/)
- [Plugin and native capability model](https://push-in.github.io/pam-docs/native/plugins/)
- [Report an issue](https://github.com/push-in/pam-native-charts/issues)

Security vulnerabilities should be reported through the repository security policy or GitHub private vulnerability reporting, not a public issue.
