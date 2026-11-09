# Charts guide

Moved out of the README; nothing was removed.

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
