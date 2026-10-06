<?php

declare(strict_types=1);

namespace Pam\Native\Charts;

/**
 * Entry point of the library: registers the chart template tags once per process.
 *
 * Charts is a plain Composer library (not a PAM Native plugin) because it builds on the
 * `pushinbr/pam-native-canvas` plugin, and plugins may only depend on the core SDK. Call
 * `Charts::register()` in your `index.php` before `App::run()`.
 */
final class Charts
{
    private static bool $registered = false;

    private function __construct()
    {
    }

    /** Registers `<LineChart>`, `<AreaChart>`, `<BarChart>`, `<DonutChart>`, `<Sparkline>` and `<ProgressRing>`. */
    public static function register(): void
    {
        if (self::$registered) {
            return;
        }
        (new ChartsPluginProvider())->register();
        self::$registered = true;
    }

    /** Forgets the registration flag (tests). */
    public static function reset(): void
    {
        self::$registered = false;
    }
}
