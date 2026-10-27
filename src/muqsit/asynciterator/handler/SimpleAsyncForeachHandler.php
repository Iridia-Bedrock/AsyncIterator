<?php

declare(strict_types=1);

namespace muqsit\asynciterator\handler;

use Closure;
use Iterator;
use muqsit\asynciterator\util\EmptyTimedClosure;
use muqsit\asynciterator\util\KeyValueTimedClosure;
use pocketmine\scheduler\TaskHandler;
use Throwable;

/**
 * @template TKey
 * @template TValue
 * @implements AsyncForeachHandler<TKey, TValue>
 */
final class SimpleAsyncForeachHandler implements AsyncForeachHandler{

	private const COMPLETION_CALLBACKS = 0;
	private const INTERRUPTION_CALLBACKS = 1;
	private const EMPTY_CALLBACKS = 2;

	private string $timings_parent_name;

	/** @var array<KeyValueTimedClosure<TKey, TValue, AsyncForeachResult>> */
	private array $callbacks = [];

	private int $finalization_type = self::COMPLETION_CALLBACKS;

	private ?TaskHandler $task_handler = null;

	private bool $finalized = false;

	private ?Closure $error_callback = null;

	/** @var array<int, array<EmptyTimedClosure>>  */
	private array $finalization_callbacks = [
		self::COMPLETION_CALLBACKS => [],
		self::INTERRUPTION_CALLBACKS => [],
		self::EMPTY_CALLBACKS => []
	];

	/**
	 * @param Iterator<TKey, TValue> $iterable
	 * @param int $entries_per_tick
	 */
	public function __construct(
		readonly private Iterator $iterable,
		readonly private int $entries_per_tick
	){
		$iterable->rewind();
	}

	public function init(string $timings_parent_name) : void{
		$this->timings_parent_name = $timings_parent_name;
	}

	public function attach(TaskHandler $task_handler) : void{
		if($this->task_handler !== null){
			throw new \LogicException('An async foreach handler is already attached to a task');
		}

		$this->task_handler = $task_handler;
	}

	public function interrupt() : void{
		if($this->finalized){
			return;
		}

		$this->cancelNext();
		$this->finalization_type = self::INTERRUPTION_CALLBACKS;
		$this->task_handler?->cancel();
	}

	public function cancel() : void{
		if($this->finalized){
			return;
		}

		$this->cancelNext();
		$this->finalization_type = self::EMPTY_CALLBACKS;
		$this->task_handler?->cancel();
	}

	public function fail(Throwable $exception) : void{
		if($this->finalized){
			return;
		}

		$this->finalized = true;
		$this->task_handler?->cancel();
		if($this->error_callback !== null){
			($this->error_callback)($exception);

			return;
		}

		throw $exception;
	}

	private function cancelNext() : void{
		$this->callbacks = [];
		$this->as(static function($key, $value) : AsyncForeachResult{ return AsyncForeachResult::CANCEL(); });
	}

	public function handle() : bool{
		if($this->finalized){
			return false;
		}

		$per_run = $this->entries_per_tick;
		while($this->iterable->valid()){
			/** @var TKey $key */
			$key = $this->iterable->key();

			/** @var TValue $value */
			$value = $this->iterable->current();

			foreach($this->callbacks as $callback){
				if(!$callback->call($key, $value)->handle($this)){
					return false;
				}
			}

			$this->iterable->next();
			if(--$per_run === 0){
				return true;
			}
		}

		return false;
	}

	public function doCompletion() : void{
		if($this->finalized){
			return;
		}

		foreach($this->finalization_callbacks[$this->finalization_type] as $callback){
			$callback->call();
		}
		$this->finalized = true;
	}

	public function as(Closure $callback) : AsyncForeachHandler{
		$this->callbacks[spl_object_id($callback)] = new KeyValueTimedClosure(AsyncForeachHandlerTimings::getTraverserTimings($this->timings_parent_name, $callback), $callback);
		return $this;
	}

	public function onCompletion(Closure $callback) : AsyncForeachHandler{
		$this->finalization_callbacks[self::COMPLETION_CALLBACKS][spl_object_id($callback)] = new EmptyTimedClosure(AsyncForeachHandlerTimings::getOnCompletionTimings($this->timings_parent_name, $callback), $callback);
		return $this;
	}

	public function onInterruption(Closure $callback) : AsyncForeachHandler{
		$this->finalization_callbacks[self::INTERRUPTION_CALLBACKS][spl_object_id($callback)] = new EmptyTimedClosure(AsyncForeachHandlerTimings::getOnInterruptionTimings($this->timings_parent_name, $callback), $callback);
		return $this;
	}

	public function onCompletionOrInterruption(Closure $callback) : AsyncForeachHandler{
		return $this->onCompletion($callback)
			->onInterruption($callback);
	}

	public function onError(Closure $callback) : AsyncForeachHandler{
		$this->error_callback = $callback;

		return $this;
	}
}
