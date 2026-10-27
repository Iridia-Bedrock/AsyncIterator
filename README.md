# AsyncIterator

`AsyncIterator` is a small PocketMine-MP 5 library for traversing an `Iterator` over several server ticks.

This repository is a maintained fork of [`Muqsit/AsyncIterator`](https://github.com/Muqsit/AsyncIterator). The original project is deprecated, so this fork keeps its public API and fixes several lifecycle and error-handling problems for projects that still rely on it.

The namespace remains `muqsit\asynciterator` to make migration straightforward.

## What this fork fixes

- Validates `entries_per_tick` and `sleep_time` before scheduling a task.
- Cancels the scheduler task immediately when a traversal is interrupted or cancelled.
- Prevents a completed traversal from being finalized more than once.
- Handles `Throwable`, including PHP `Error` instances, raised by iterator callbacks and completion callbacks.
- Adds an `onError()` callback for controlled error handling.
- Keeps callback state isolated between concurrent traversals.
- Preserves the existing continue, interrupt, cancel, completion, and timing behavior.

## Installation

The library is distributed as a Virion and targets PocketMine-MP 5.0.0 or later.

For a project using the Iridia library updater:

```json
{
    "libraries": {
        "Iridia-Bedrock/AsyncIterator": {
            "version": "pm5",
            "refType": "branch"
        }
    }
}
```

The fork keeps the original `muqsit\asynciterator` namespace and is intended to be a drop-in replacement for the deprecated upstream project.

## Basic usage

```php
use ArrayIterator;
use muqsit\asynciterator\AsyncIterator;
use muqsit\asynciterator\handler\AsyncForeachResult;
use pocketmine\plugin\Plugin;

/** @var Plugin $plugin */
$asyncIterator = new AsyncIterator($plugin->getScheduler());

$task = $asyncIterator->forEach(
    new ArrayIterator(['first', 'second', 'third']),
    entries_per_tick: 1,
    sleep_time: 1
);

$task
    ->as(function (int $key, string $value): AsyncForeachResult {
        // Keep each callback short; it runs on the main server thread.
        echo $value . PHP_EOL;

        return AsyncForeachResult::CONTINUE();
    })
    ->onCompletion(function (): void {
        echo 'Traversal completed.' . PHP_EOL;
    });
```

With `entries_per_tick: 1`, one item is processed every `sleep_time` ticks. The iterator is always traversed in its natural order and registered `as()` callbacks run in insertion order for each item.

## Handling errors

Exceptions and PHP errors raised while processing an item are caught by the task. Register `onError()` when the caller should handle them without letting the scheduler task fail silently:

```php
use Throwable;

$task
    ->as(function (int $key, mixed $value): AsyncForeachResult {
        if ($value === 'invalid') {
            throw new RuntimeException('Invalid value.');
        }

        return AsyncForeachResult::CONTINUE();
    })
    ->onError(function (Throwable $exception): void {
        // The traversal is stopped before this callback is called.
        $plugin->getLogger()->error($exception->getMessage());
    });
```

If no error callback is registered, the original `Throwable` is rethrown after the scheduler task is cancelled.

## Controlling a traversal

An `as()` callback must return an `AsyncForeachResult`:

```php
$task
    ->as(function (int $key, mixed $value): AsyncForeachResult {
        if ($value === 'stop') {
            return AsyncForeachResult::INTERRUPT();
        }

        if ($value === 'discard') {
            return AsyncForeachResult::CANCEL();
        }

        return AsyncForeachResult::CONTINUE();
    })
    ->onInterruption(function (): void {
        echo 'Traversal interrupted.' . PHP_EOL;
    });
```

| Result or method | Behavior |
| --- | --- |
| `CONTINUE()` | Processes the next item. |
| `INTERRUPT()` | Stops the traversal and runs interruption callbacks. |
| `CANCEL()` | Stops and disposes the task without completion or interruption callbacks. |
| `interrupt()` | Interrupts an active traversal immediately. |
| `cancel()` | Cancels an active traversal immediately. |
| `onCompletion()` | Runs after the iterator is naturally exhausted. |
| `onInterruption()` | Runs after an interruption. |
| `onCompletionOrInterruption()` | Runs after either completion or interruption. |
| `onError()` | Receives a `Throwable` raised during traversal. |

Example of external control:

```php
$task = $asyncIterator->forEach(new ArrayIterator($values))
    ->as(static fn(int $key, mixed $value): AsyncForeachResult => AsyncForeachResult::CONTINUE())
    ->onCompletion(static function (): void {
        echo 'Done.' . PHP_EOL;
    });

// Keep the returned handler and call this from another event or task when needed.
$task->interrupt();
```

## Timing and limits

```php
$task = $asyncIterator->forEach(
    $iterator,
    entries_per_tick: 50,
    sleep_time: 2
);
```

- `entries_per_tick` must be greater than zero.
- `sleep_time` must be greater than zero and is measured in server ticks.
- The default values are `10` entries per run and `1` tick between runs.
- The callbacks run on PocketMine-MP's main thread. Use a separate asynchronous worker for blocking or CPU-heavy work, then use `AsyncIterator` to apply the result incrementally.

## Compatibility

- PHP 8.1+
- PocketMine-MP 5.0.0+
- Virion specification 3.0

The public API is kept compatible with the upstream library. The `onError()` method is an additive extension.

## Credits and license

Original library by [Muqsit](https://github.com/Muqsit).

This fork is maintained by [Iridia Bedrock](https://github.com/Iridia-Bedrock) and remains licensed under the [GNU GPL-3.0](LICENSE).
