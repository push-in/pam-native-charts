# Contributing

Moved out of the README; nothing was removed.

```bash
composer install
php tests/run.php            # scene snapshots, scales, curves, template registration
vendor/bin/phpstan analyse   # level max
```

Every chart type has a snapshot test on its display list; add one when a chart's marks change.
Drawing primitives belong in [`pam-native-canvas`](https://github.com/push-in/pam-native-canvas);
this library only composes scenes.
