<?php

declare(strict_types=1);

// The same charts from PHP builders (e.g. from a component's render method or a test).

use Pam\Native\Charts\ChartTheme;
use Pam\Native\Charts\Curve;
use Pam\Native\Charts\DonutChart;
use Pam\Native\Charts\LineChart;
use Pam\Native\Charts\ProgressRing;
use Pam\Native\Charts\Series;
use Pam\Native\Charts\Slice;

$theme = ChartTheme::dark(primary: '#19c5ff', ink: '#f2f7ff', muted: '#8496b0', grid: '#2c3947', surface: '#101a2c');

$balance = LineChart::make(width: 328, height: 160)
    ->theme($theme)
    ->series(Series::make('Saldo', [1200.5, 980, 1340, 1290, 1800, 1750, 2100]))
    ->labels(['seg', 'ter', 'qua', 'qui', 'sex', 'sáb', 'dom'])
    ->curve(Curve::Monotone)
    ->area()
    ->grid(3)
    ->valueFormatter(static fn (float $value): string => 'R$ '.number_format($value, 0, ',', '.'))
    ->highlight(4)
    ->onSelect(static function (?int $index): void { /* re-render with ->highlight($index) */ });

$methods = DonutChart::make(size: 160)
    ->theme($theme)
    ->slices([Slice::make('Pix', 70.0), Slice::make('Boleto', 20.0), Slice::make('Cartão', 10.0)])
    ->thickness(18)
    ->center(title: 'R$ 1.234', subtitle: 'este mês');

$goal = ProgressRing::make(size: 56)->progress(0.42)->label('42%')->color('#19c5ff')->track('#2c3947');

// Each builder is Renderable; `->view()` returns the CanvasView and `->scene()` the display list.
return [$balance, $methods, $goal];
