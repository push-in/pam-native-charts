<?php

declare(strict_types=1);

namespace Pam\Native\Charts;

/**
 * Where the series legend is drawn, inside the chart bounds.
 */
enum LegendPosition: int
{
    case None = 1;
    case Top = 2;
    case Bottom = 3;
}
