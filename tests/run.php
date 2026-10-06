<?php

declare(strict_types=1);

use Pam\Native\Canvas\CanvasCommandKind;
use Pam\Native\Canvas\CanvasEventKind;
use Pam\Native\Canvas\CanvasScene;
use Pam\Native\Charts\AreaChart;
use Pam\Native\Charts\BarChart;
use Pam\Native\Charts\BarLayout;
use Pam\Native\Charts\ChartPalette;
use Pam\Native\Charts\ChartsPluginProvider;
use Pam\Native\Charts\ChartTheme;
use Pam\Native\Charts\Curve;
use Pam\Native\Charts\DonutChart;
use Pam\Native\Charts\Internal\Color;
use Pam\Native\Charts\Internal\LinearScale;
use Pam\Native\Charts\Internal\MonotoneCurve;
use Pam\Native\Charts\LegendPosition;
use Pam\Native\Charts\LineChart;
use Pam\Native\Charts\ProgressRing;
use Pam\Native\Charts\Series;
use Pam\Native\Charts\Slice;
use Pam\Native\Charts\Sparkline;
use Pam\Native\Element;
use Pam\Native\Internal\Wire;
use Pam\Native\TemplateRegistry;
use Pam\Native\UI\CustomView;

$packageAutoload = dirname(__DIR__) . '/vendor/autoload.php';

if (is_file($packageAutoload)) {
    require $packageAutoload;
}

$roots = [
    'Pam\\Native\\Charts\\' => dirname(__DIR__) . '/src/',
    'Pam\\Native\\Canvas\\' => dirname(__DIR__, 2) . '/pam-native-canvas/src/',
    'Pam\\Native\\' => dirname(__DIR__, 2) . '/pam-native/packages/native/src/',
];

spl_autoload_register(static function (string $class) use ($roots): void {
    foreach ($roots as $prefix => $root) {
        if (str_starts_with($class, $prefix)) {
            $file = $root . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

            if (is_file($file)) {
                require $file;
            }

            return;
        }
    }
}, true, true);

/** @var array<string, Closure(): void> $tests */
$tests = [];
$test = static function (string $name, Closure $body) use (&$tests): void {
    $tests[$name] = $body;
};

function expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @return list<int> */
function kinds(CanvasScene $scene): array
{
    return array_map(static fn ($command): int => $command->kind->value, $scene->commands);
}

function countKind(CanvasScene $scene, CanvasCommandKind $kind): int
{
    return count(array_filter(kinds($scene), static fn (int $value): bool => $value === $kind->value));
}

function sampleLine(): LineChart
{
    return LineChart::make(width: 328, height: 160)
        ->theme(ChartTheme::dark(primary: '#19c5ff', ink: '#f4f7fa', muted: '#a0adbb', grid: '#2c3947', surface: '#19222c'))
        ->series(Series::make('Entradas', [120.5, 80, 140, 90, 200])->color('#19c5ff'))
        ->series(Series::make('Saídas', [40, 60, 30, 80, 50])->color('#ff8990'))
        ->labels(['Seg', 'Ter', 'Qua', 'Qui', 'Sex'])
        ->curve(Curve::Monotone)
        ->area(true)
        ->grid(3)
        ->valueFormatter(static fn (float $v): string => 'R$ ' . number_format($v, 0, ',', '.'))
        ->highlight(2);
}

$test('all coded variants are sequential integers', static function (): void {
    foreach ([Curve::cases(), LegendPosition::cases(), BarLayout::cases()] as $cases) {
        $values = array_map(static fn ($case) => $case->value, $cases);
        expect($values === range(1, count($values)), 'non-sequential enum');
    }
});

$test('colors normalize to #rrggbbaa and reject other forms', static function (): void {
    expect(Color::normalize('#abc') === '#aabbccff', 'short form');
    expect(Color::normalize('#19C5FF') === '#19c5ffff', 'six digits');
    expect(Color::normalize('#19c5ff80') === '#19c5ff80', 'eight digits');
    expect(Color::alpha('#19c5ff', 0.35) === '#19c5ff59', 'alpha replaced');
    expect(abs(Color::opacity('#00000000')) < 1e-9, 'opacity read');

    foreach (['19c5ff', '#12', 'red', '#gggggg', '#1234567'] as $invalid) {
        try {
            Color::normalize($invalid);
            throw new RuntimeException("accepted {$invalid}");
        } catch (InvalidArgumentException) {
        }
    }
});

$test('linear scales pick nice 1-2-5 ticks', static function (): void {
    expect(LinearScale::nice(0, 200, 3)->ticks() === [0.0, 100.0, 200.0], '0..200');
    expect(LinearScale::nice(0, 0.42, 4)->ticks() === [0.0, 0.2, 0.4, 0.6], '0..0.42');
    $scale = LinearScale::nice(-30, 70, 5);
    expect($scale->min === -40.0 && $scale->max === 80.0 && $scale->step === 20.0, '-30..70');
    $empty = LinearScale::nice(0, 0, 3);
    expect($empty->min === 0.0 && $empty->max === 1.0 && $empty->ticks() === [0.0, 0.5, 1.0], 'empty domain');
    expect(LinearScale::nice(0, 1234, 3)->ticks() === [0.0, 500.0, 1000.0, 1500.0], '0..1234');
    expect(LinearScale::nice(0, 10, 2)->position(5, 100, 0) === 50.0, 'position maps linearly');
});

$test('monotone curves never overshoot their neighbours', static function (): void {
    $points = [[0.0, 10.0], [10.0, 80.0], [20.0, 20.0], [30.0, 25.0], [40.0, 90.0], [50.0, 90.0], [60.0, 0.0]];
    $segments = MonotoneCurve::segments($points);
    expect(count($segments) === 6, 'one segment per interval');

    foreach ($segments as $i => [$c1x, $c1y, $c2x, $c2y, $x, $y]) {
        $low = min($points[$i][1], $points[$i + 1][1]);
        $high = max($points[$i][1], $points[$i + 1][1]);
        expect($c1y >= $low - 1e-9 && $c1y <= $high + 1e-9, "c1 of segment {$i} overshoots");
        expect($c2y >= $low - 1e-9 && $c2y <= $high + 1e-9, "c2 of segment {$i} overshoots");
        expect($c1x >= $points[$i][0] && $c2x <= $x, "controls of segment {$i} leave the x range");
        expect($y === $points[$i + 1][1], 'segment ends on the point');
    }

    expect(MonotoneCurve::segments([[0.0, 1.0]]) === [], 'single point has no segments');
});

$test('line chart scene snapshot', static function (): void {
    $scene = sampleLine()->scene();
    $kinds = kinds($scene);
    expect(count($kinds) === 23, 'command count changed: ' . count($kinds));
    expect($kinds[0] === CanvasCommandKind::FillRect->value, 'starts with the surface');
    expect(end($kinds) === CanvasCommandKind::Label->value, 'ends with an x label');
    expect(countKind($scene, CanvasCommandKind::GradientPath) === 2, 'one area per series');
    expect(countKind($scene, CanvasCommandKind::Path) === 2, 'one line per series');
    expect(countKind($scene, CanvasCommandKind::DashedLine) === 3, 'two grid lines and one crosshair');
    expect(countKind($scene, CanvasCommandKind::Circle) === 4, 'two markers with knockouts');
    expect(json_encode(json_decode($scene->toJson()))  === $scene->toJson(), 'json is stable');
    expect(CustomView::class === sampleLine()->view()->toElement()::class, 'renders a custom view');
});

$test('area chart is a line chart with the area on by default', static function (): void {
    $scene = AreaChart::make(200, 100)->series(Series::make('a', [1, 2, 3]))->scene();
    expect(countKind($scene, CanvasCommandKind::GradientPath) === 1, 'area present');
    expect(countKind($scene, CanvasCommandKind::Path) === 1, 'line present');
});

$test('line chart handles empty and single-point series', static function (): void {
    $empty = LineChart::make(200, 100)->series(Series::make('a', []))->labels(['x'])->scene();
    $kinds = kinds($empty);
    expect($kinds === [CanvasCommandKind::FillRect->value, CanvasCommandKind::Line->value, CanvasCommandKind::Label->value], 'baseline + Sem dados');
    expect(str_contains($empty->toJson(), 'Sem dados'), 'muted empty label');

    $single = LineChart::make(200, 100)->series(Series::make('a', [42]))->area()->scene();
    expect(countKind($single, CanvasCommandKind::Circle) === 1, 'single point drawn as a marker');
    expect(countKind($single, CanvasCommandKind::Path) === 0, 'no path for a single point');
    expect(LineChart::make(200, 100)->series(Series::make('a', [42]))->indexAt(100) === 0, 'single point index');
});

$test('long series are split into several path commands under the spec cap', static function (): void {
    $values = array_map(static fn (int $i): float => sin($i / 9) * 50 + 60, range(0, 299));
    $scene = LineChart::make(400, 200)->series(Series::make('x', $values))->curve(Curve::Monotone)->area()->scene();
    expect(countKind($scene, CanvasCommandKind::Path) >= 4, 'line was chunked');
    expect(countKind($scene, CanvasCommandKind::GradientPath) === countKind($scene, CanvasCommandKind::Path), 'areas match lines');

    foreach ($scene->commands as $command) {
        foreach ($command->arguments as $argument) {
            expect(!is_string($argument) || strlen($argument) <= 4096, 'spec within the canvas cap');
        }
    }
});

$test('bar chart scene snapshot, grouped and stacked', static function (): void {
    $chart = BarChart::make(width: 328, height: 160)
        ->series(Series::make('Pix', [12, 18, 9, 22]))
        ->series(Series::make('Cartão', [8, 6, 11, 4]))
        ->labels(['Jan', 'Fev', 'Mar', 'Abr'])
        ->legend(LegendPosition::Bottom)
        ->highlight(1);

    $grouped = $chart->scene();
    $kinds = kinds($grouped);
    expect(countKind($grouped, CanvasCommandKind::Path) === 8, 'one bar per value');
    expect(countKind($grouped, CanvasCommandKind::RoundRect) === 1, 'highlight backdrop');
    expect(kinds($grouped)[0] === CanvasCommandKind::FillRect->value, 'starts with the surface');
    expect(end($kinds) === CanvasCommandKind::Label->value, 'ends with a label');
    expect(countKind($grouped, CanvasCommandKind::Label) >= 2 + 4 + 2, 'ticks, x labels, legend and highlight values');

    $stacked = $chart->stacked()->scene();
    expect(countKind($stacked, CanvasCommandKind::Path) === 4, 'one rounded top segment per category');
    expect(countKind($stacked, CanvasCommandKind::FillRect) === 1 + 4, 'surface plus one plain segment per category');
    $tall = $chart->series(Series::make('Boleto', [15, 15, 15, 15]));
    expect($tall->scale()->max === 30.0, 'grouped domain uses the single maximum');
    expect($tall->stacked()->scale()->max === 60.0, 'stacked domain uses sums');

    $negative = BarChart::make(200, 100)->series(Series::make('a', [-5, 10]))->scene();
    expect(countKind($negative, CanvasCommandKind::Path) === 2, 'negative bars drawn');
    expect(BarChart::make(200, 100)->series(Series::make('a', []))->scene()->commands !== [], 'empty bar chart draws');
});

$test('donut chart draws gapped sectors, center labels and a legend', static function (): void {
    $donut = DonutChart::make(size: 160)
        ->slices([Slice::make('Pix', 70.0)->color('#19c5ff'), Slice::make('Cartão', 30.0), Slice::make('Zero', 0)])
        ->thickness(18)
        ->gap(2)
        ->center(title: 'R$ 1.234', subtitle: 'este mês');
    $scene = $donut->scene();
    expect(countKind($scene, CanvasCommandKind::Sector) === 2, 'zero slices are skipped');
    expect(countKind($scene, CanvasCommandKind::Label) === 2, 'title and subtitle');

    $sweep = 0.0;
    foreach ($scene->commands as $command) {
        if ($command->kind === CanvasCommandKind::Sector) {
            $sweep += (float) $command->arguments[5];
            expect((float) $command->arguments[3] === 62.0, 'inner radius from thickness');
        }
    }
    expect($sweep < 360.0 && $sweep > 350.0, 'gaps trimmed from sweeps');

    $legend = $donut->legend(LegendPosition::Bottom);
    expect($legend->height() === 160.0 + 3 * DonutChart::LEGEND_ROW_HEIGHT + 8.0, 'legend grows the height');
    expect(countKind($legend->scene(), CanvasCommandKind::Circle) === 3, 'one swatch per slice');

    $empty = DonutChart::make(80)->scene();
    expect(countKind($empty, CanvasCommandKind::Sector) === 1 && str_contains($empty->toJson(), 'Sem dados'), 'empty track');
});

$test('sparkline and progress ring snapshots', static function (): void {
    $spark = Sparkline::make(width: 96, height: 32)->values([3, 5, 4, 8, 6])->color('#19c5ff')->area(true)->curve(Curve::Monotone)->endMarker();
    $scene = $spark->scene();
    expect(kinds($scene) === [8, 21, 19, 10, 10], 'surface, area, line, marker knockout, marker');
    expect(kinds(Sparkline::make(96, 32)->scene()) === [8, 11], 'empty sparkline draws a baseline');
    expect(countKind(Sparkline::make(96, 32)->values([7])->scene(), CanvasCommandKind::Circle) === 1, 'single value marker');

    $ring = ProgressRing::make(size: 56)->progress(0.42)->thickness(6)->color('#19c5ff')->track('#2c3947')->label('42%');
    $scene = $ring->scene();
    expect(kinds($scene) === [8, 15, 15, 23], 'surface, track, arc, label');
    expect((float) $scene->commands[2]->arguments[4] === 151.2, 'sweep from progress');
    expect($scene->commands[2]->arguments[7] === 2, 'round cap');
    expect(kinds(ProgressRing::make(40)->progress(0)->scene()) === [8, 15], 'no arc at zero');
    $full = ProgressRing::make(40)->progress(7)->scene();
    expect(kinds($full) === [8, 15, 15] && (float) $full->commands[2]->arguments[4] === 360.0, 'progress clamps to 1');
});

$test('highlight index resolves from pointer x', static function (): void {
    $line = sampleLine();
    [$px, , $pw] = (new ReflectionMethod($line, 'plotRect'))->invoke($line);
    expect($line->indexAt($px) === 0, 'first point');
    expect($line->indexAt($px + $pw) === 4, 'last point');
    expect($line->indexAt($px + $pw / 2 + 3) === 2, 'nearest middle point');
    expect($line->indexAt($px + $pw * 0.3) === 1, 'rounds to nearest');
    expect($line->indexAt(-50) === null && $line->indexAt(1000) === null, 'outside returns null');
    expect(LineChart::make(100, 50)->indexAt(50) === null, 'no data returns null');

    $bar = BarChart::make(328, 160)->series(Series::make('a', [1, 2, 3, 4]));
    [$px, , $pw] = (new ReflectionMethod($bar, 'plotRect'))->invoke($bar);
    expect($bar->indexAt($px + 1) === 0, 'first slot');
    expect($bar->indexAt($px + $pw * 0.6) === 2, 'third slot');
    expect($bar->indexAt($px + $pw) === 3, 'edge clamps to the last slot');
    expect($bar->indexAt($px - 1) === null, 'left of the plot');
});

$test('pointer events select the nearest index through the canvas view', static function (): void {
    $selected = [];
    $chart = sampleLine()->onSelect(static function (?int $index) use (&$selected): void {
        $selected[] = $index;
    });
    [$px, , $pw] = (new ReflectionMethod($chart, 'plotRect'))->invoke($chart);
    $events = $chart->toElement()->events();
    expect($events !== [], 'pointer handler wired');
    $handler = reset($events);
    expect($handler instanceof Closure, 'native event closure');
    $handler(Wire::map(['event' => CanvasEventKind::PointerDown->value, 'x' => $px + $pw, 'y' => 10.0]));
    $handler(Wire::map(['event' => CanvasEventKind::PointerMove->value, 'x' => $px, 'y' => 10.0]));
    $handler(Wire::map(['event' => CanvasEventKind::PointerUp->value, 'x' => $px, 'y' => 10.0]));
    $handler(Wire::map(['event' => CanvasEventKind::PointerCancel->value, 'x' => $px, 'y' => 10.0]));
    expect($selected === [4, 0, null], 'down/move select, up keeps, cancel clears: ' . json_encode($selected));
    expect(sampleLine()->toElement()->events() === [], 'static charts have no handler');
});

$test('revision changes with data and stays stable otherwise', static function (): void {
    $base = sampleLine();
    expect($base->revision() === sampleLine()->revision(), 'same inputs, same revision');
    expect($base->revision() !== $base->highlight(3)->revision(), 'highlight changes the revision');
    expect($base->revision() !== $base->series(Series::make('c', [1, 2, 3, 4, 5]))->revision(), 'series change the revision');
    expect($base->revision() > 0, 'revision is positive');
    expect($base->view()->toElement() instanceof Element, 'view renders');
});

$test('builders are immutable and validate inputs', static function (): void {
    $base = LineChart::make(100, 50);
    expect($base !== $base->area(), 'area clones');
    expect($base !== $base->series(Series::make('a', [1])), 'series clones');
    expect($base->scene()->commands !== [] && count($base->series(Series::make('a', [1]))->scene()->commands) > count($base->scene()->commands), 'clone carries the change');

    foreach ([
        static fn () => Series::make('a', [NAN]),
        static fn () => Series::make('a', [INF]),
        static fn () => Series::make('a', ['1']),
        static fn () => Series::make('a', [1])->color('blue'),
        static fn () => Slice::make('a', -1),
        static fn () => LineChart::make(0, 10),
        static fn () => LineChart::make(10, 5000),
        static fn () => DonutChart::make(100)->thickness(80),
        static fn () => ProgressRing::make(40)->progress(NAN),
        static fn () => BarChart::make(100, 50)->barRadius(-1),
        static fn () => ChartPalette::make(),
    ] as $i => $invalid) {
        try {
            $invalid();
            throw new RuntimeException("invalid input {$i} accepted");
        } catch (InvalidArgumentException) {
        }
    }
});

$test('themes and palettes assign series colors in fixed order', static function (): void {
    $theme = ChartTheme::dark(primary: '#ff00ff');
    expect($theme->palette->color(0) === '#ff00ffff', 'primary leads the palette');
    expect($theme->palette->color(1) === '#19c5ffff', 'then the categorical order');
    expect($theme->palette->color(99) === $theme->palette->color(99 % count($theme->palette->colors)), 'wraps');
    expect($theme->grid === Color::alpha('#f4f7fa', 0.12), 'grid defaults to 12% ink');
    expect(ChartTheme::light()->ink === '#101828ff', 'light theme');
    expect(ChartTheme::fromArray(['primary' => '#123456', 'ink' => '#fff'])->ink === '#ffffffff', 'from template array');
    expect(ChartTheme::fromArray(['primary' => '#123456'])->palette->color(0) === '#123456ff', 'array primary leads the palette');
    $sequential = ChartPalette::sequential('#19c5ff', 3);
    expect($sequential->colors === ['#19c5ff59', '#19c5ffac', '#19c5ffff'], 'light to dark ramp');
    expect($theme->seriesColor('#000000ff', 3) === '#000000ff', 'explicit colors win');
});

$test('registers the declarative chart tags', static function (): void {
    TemplateRegistry::reset();
    (new ChartsPluginProvider())->register();

    foreach (['LineChart', 'AreaChart', 'BarChart', 'DonutChart', 'Sparkline', 'ProgressRing'] as $tag) {
        expect(TemplateRegistry::factory($tag) instanceof Closure, "{$tag} missing");
    }

    $selected = [];
    $factory = TemplateRegistry::factory('LineChart');
    expect($factory !== null, 'line factory');
    $chart = $factory([
        'series' => [
            ['label' => 'Entradas', 'values' => [120.5, 80, 140, 90, 200], 'color' => '#19c5ff'],
            ['label' => 'Saídas', 'values' => ['40', '60', '30', '80', '50']],
        ],
        'labels' => ['Seg', 'Ter', 'Qua', 'Qui', 'Sex'],
        'width' => '328',
        'height' => '160',
        'curve' => 'monotone',
        'area' => 'true',
        'grid' => '3',
        'highlight' => '2',
        'legend' => 'top',
        'theme' => ['primary' => '#19c5ff', 'ink' => '#f4f7fa'],
        '__pamComponentEvents' => ['select' => static function (?int $index) use (&$selected): void {
            $selected[] = $index;
        }],
    ], [], null);
    expect($chart instanceof LineChart, 'line chart built from props');
    expect($chart->width() === 328.0 && $chart->height() === 160.0, 'string sizes coerced');
    $scene = $chart->scene();
    expect(countKind($scene, CanvasCommandKind::GradientPath) === 2, 'area="true" coerced');
    expect(countKind($scene, CanvasCommandKind::Circle) === 4 + 2, 'highlight markers plus legend swatches');
    $element = $chart->toElement();
    expect($element instanceof Element && $element->events() !== [], '@select wired');
    $events = $element->events();
    $handler = reset($events);
    expect($handler instanceof Closure, 'event closure');
    $handler(Wire::map(['event' => 1, 'x' => 400.0, 'y' => 1.0]));
    expect($selected === [null], 'select event forwarded');

    $area = TemplateRegistry::factory('AreaChart');
    expect($area !== null && countKind($area(['values' => [1, 2, 3]], [], null)->scene(), CanvasCommandKind::GradientPath) === 1, 'area tag defaults to area');

    $bar = TemplateRegistry::factory('BarChart');
    expect($bar !== null, 'bar factory');
    $barChart = $bar(['series' => [['label' => 'a', 'values' => '1,2,3'], ['label' => 'b', 'values' => '3,2,1']], 'stacked' => 'true', 'labels' => 'x,y,z'], [], null);
    expect($barChart instanceof BarChart && $barChart->scale()->max === 4.0, 'stacked="true" coerced');

    $donut = TemplateRegistry::factory('DonutChart');
    expect($donut !== null, 'donut factory');
    $donutChart = $donut(['size' => '120', 'thickness' => '14', 'title' => 'R$ 10', 'subtitle' => 'hoje', 'slices' => [['label' => 'Pix', 'value' => '70', 'color' => '#19c5ff'], ['label' => 'Cartão', 'value' => 30]]], [], null);
    expect($donutChart instanceof DonutChart && countKind($donutChart->scene(), CanvasCommandKind::Sector) === 2, 'donut from props');

    $spark = TemplateRegistry::factory('Sparkline');
    expect($spark !== null, 'sparkline factory');
    $sparkline = $spark(['values' => '3, 5, 4, 8', 'area' => 'true', 'curve' => 'monotone', 'color' => '#19c5ff'], [], null);
    expect($sparkline instanceof Sparkline && countKind($sparkline->scene(), CanvasCommandKind::GradientPath) === 1, 'sparkline from props');

    $ring = TemplateRegistry::factory('ProgressRing');
    expect($ring !== null, 'ring factory');
    $progress = $ring(['size' => '56', 'progress' => '0.42', 'thickness' => '6', 'label' => '42%'], [], null);
    expect($progress instanceof ProgressRing && kinds($progress->scene()) === [8, 15, 15, 23], 'ring from props');

    TemplateRegistry::reset();
});

$failed = 0;

foreach ($tests as $name => $body) {
    try {
        $body();
        fwrite(STDOUT, "PASS {$name}\n");
    } catch (Throwable $error) {
        $failed++;
        fwrite(STDERR, "FAIL {$name}: {$error->getMessage()} ({$error->getFile()}:{$error->getLine()})\n");
    }
}

fwrite(STDOUT, count($tests) . " tests, {$failed} failures\n");
exit($failed === 0 ? 0 : 1);
