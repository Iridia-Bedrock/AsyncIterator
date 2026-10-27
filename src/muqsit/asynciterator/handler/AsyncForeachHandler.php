<?php

declare(strict_types=1);

namespace muqsit\asynciterator\handler;

use Closure;
use pocketmine\scheduler\TaskHandler;
use Throwable;

/**
 * @template TKey
 * @template TValue
 */
interface AsyncForeachHandler{

	/**
	 * Called on each iterated entry. Should accept two parameters,
	 * the first being the key, and the second being the value,
	 * and should return an {@see AsyncForeachResult} instance.
	 *
	 * @param Closure(TKey, TValue) : AsyncForeachResult $callback
	 * @return AsyncForeachHandler<TKey, TValue>
	 */
	public function as(Closure $callback) : self;

	/**
	 * Stops the foreach task, triggering interrupt listeners
	 * immediately.
	 */
	public function interrupt() : void;

	/**
	 * @param TaskHandler $task_handler
	 */
	public function attach(TaskHandler $task_handler) : void;

	/**
	 * Stops the foreach task.
	 *
	 * WARNING: Cancelling a foreach task will trigger NEITHER
	 * {@see AsyncForeachHandler::onInterruption()} callbacks, nor
	 * {@see AsyncForeachHandler::onCompletion()} callbacks.
	 */
	public function cancel() : void;

	/**
	 * @param Throwable $exception
	 */
	public function fail(Throwable $exception) : void;

	/**
	 * @param Closure(Throwable): void $callback
	 * @return AsyncForeachHandler<TKey, TValue>
	 */
	public function onError(Closure $callback) : self;

	/**
	 * Called after the foreach task is completed successfully.
	 *
	 * @param Closure() : void $callback
	 * @return AsyncForeachHandler<TKey, TValue>
	 */
	public function onCompletion(Closure $callback) : self;

	/**
	 * Called after the foreach task is interrupted.
	 *
	 * @param Closure() : void $callback
	 * @return AsyncForeachHandler<TKey, TValue>
	 */
	public function onInterruption(Closure $callback) : self;

	/**
	 * Called after the foreach task is either completed or interrupted.
	 *
	 * @param Closure() : void $callback
	 * @return AsyncForeachHandler<TKey, TValue>
	 */
	public function onCompletionOrInterruption(Closure $callback) : self;
}
