<?php

declare(strict_types=1);

namespace Pam\Native\Charts;

/**
 * Interpolation used between consecutive points of a line, area or sparkline.
 */
enum Curve: int
{
    /** Straight segments between points. */
    case Linear = 1;

    /** Monotone cubic (Fritsch–Carlson) segments: smooth and never overshooting the data. */
    case Monotone = 2;
}
