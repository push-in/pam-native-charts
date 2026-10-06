<?php

declare(strict_types=1);

namespace Pam\Native\Charts;

use Pam\Native\Canvas\Canvas;
use Pam\Native\Canvas\PathMode;
use Pam\Native\Charts\Internal\Color;
use Pam\Native\Charts\Internal\Paths;

/**
 * Line chart with one value axis, optional gradient area, grid, legend,
 * highlight crosshair and pointer selection.
 *
 * ```php
 * LineChart::make(width: 328, height: 160)
 *     ->series(Series::make('Entradas', [120.5, 80, 140, 90, 200])->color('#19c5ff'))
 *     ->labels(['Seg', 'Ter', 'Qua', 'Qui', 'Sex'])
 *     ->curve(Curve::Monotone)
 *     ->area()
 *     ->highlight(2);
 * ```
 *
 * @phpstan-consistent-constructor
 */
class LineChart extends CartesianChart
{
    protected Curve $curve = Curve::Linear;
    protected bool $area = false;

    public static function make(float|int $width, float|int $height): static
    {
        return new static((float) $width, (float) $height);
    }

    /** Interpolation between points. */
    public function curve(Curve $curve): static
    {
        $copy = clone $this;
        $copy->curve = $curve;

        return $copy;
    }

    /** Fills under each line with a gradient from 35% of the series color to transparent. */
    public function area(bool $enabled = true): static
    {
        $copy = clone $this;
        $copy->area = $enabled;

        return $copy;
    }

    public function indexAt(float $x): ?int
    {
        $count = $this->pointCount();

        if ($count === 0) {
            return null;
        }

        [$px, , $pw] = $this->plotRect();

        if ($x < $px - self::PADDING || $x > $px + $pw + self::PADDING) {
            return null;
        }

        if ($count === 1) {
            return 0;
        }

        $ratio = max(0.0, min(1.0, ($x - $px) / $pw));

        return (int) round($ratio * ($count - 1));
    }

    protected function draw(Canvas $canvas): Canvas
    {
        $canvas = $this->paintSurface($canvas);
        [$px, $py, $pw, $ph] = $this->plotRect();
        $canvas = $this->paintLegend($canvas, $px, $pw);

        if (!$this->hasData()) {
            return $this->paintEmpty($canvas, $px, $py, $pw, $ph);
        }

        $scale = $this->scale();
        $centers = $this->centers($px, $pw);
        $baseline = round($scale->position(0.0, $py + $ph, $py), 2);
        $canvas = $this->paintGrid($canvas, $scale, $px, $py, $pw, $ph);

        foreach ($this->series as $index => $series) {
            $points = $this->points($series, $scale, $centers, $py, $ph);

            if ($points === []) {
                continue;
            }

            $color = $this->theme->seriesColor($series->color, $index);

            if ($this->area && count($points) > 1) {
                foreach (Paths::area($points, $this->curve, $baseline) as $path) {
                    $canvas = $canvas->gradientPath($path, 0, $py, 0, $py + $ph, Color::alpha($color, 0.35), Color::alpha($color, 0.0));
                }
            }

            if (count($points) === 1) {
                $canvas = $canvas->circle($points[0][0], $points[0][1], self::MARKER_RADIUS, $color);
            } else {
                foreach (Paths::line($points, $this->curve) as $path) {
                    $canvas = $canvas->path($path, $color, self::LINE_WIDTH, PathMode::Stroke);
                }
            }

            if ($this->valueLabels) {
                foreach ($points as $i => [$x, $y]) {
                    $canvas = $this->paintValueLabel($canvas, $series->values[$i], $x, $y);
                }
            }
        }

        $canvas = $this->paintHighlight($canvas, $scale, $centers, $py, $ph);

        return $this->paintLabels($canvas, $centers, $px, $pw, $this->height - self::PADDING
            - ($this->legend === LegendPosition::Bottom ? self::LEGEND_HEIGHT : 0.0) - 4.0);
    }

    /**
     * @return list<float>
     */
    protected function centers(float $px, float $pw): array
    {
        $count = $this->pointCount();

        if ($count === 1) {
            return [round($px + $pw / 2, 2)];
        }

        $centers = [];

        for ($i = 0; $i < $count; $i++) {
            $centers[] = round($px + $pw * $i / ($count - 1), 2);
        }

        return $centers;
    }

    /**
     * @param list<float> $centers
     * @return list<array{0: float, 1: float}>
     */
    private function points(Series $series, Internal\LinearScale $scale, array $centers, float $py, float $ph): array
    {
        $points = [];

        foreach ($series->values as $i => $value) {
            $points[] = [$centers[$i], round($scale->position($value, $py + $ph, $py), 2)];
        }

        return $points;
    }

    /**
     * @param list<float> $centers
     */
    private function paintHighlight(Canvas $canvas, Internal\LinearScale $scale, array $centers, float $py, float $ph): Canvas
    {
        $index = $this->highlight;

        if ($index === null || !isset($centers[$index])) {
            return $canvas;
        }

        $x = $centers[$index];
        $canvas = $canvas->dashedLine($x, $py, $x, $py + $ph, $this->theme->muted, 1, 2, 3);

        foreach ($this->series as $seriesIndex => $series) {
            if (!isset($series->values[$index])) {
                continue;
            }

            $value = $series->values[$index];
            $y = round($scale->position($value, $py + $ph, $py), 2);
            $color = $this->theme->seriesColor($series->color, $seriesIndex);
            $canvas = $canvas
                ->circle($x, $y, self::MARKER_RADIUS + 2.0, $this->theme->surface)
                ->circle($x, $y, self::MARKER_RADIUS, $color);

            if (!$this->valueLabels) {
                $canvas = $this->paintValueLabel($canvas, $value, $x, $y, true);
            }
        }

        return $canvas;
    }
}
