<?php
declare(strict_types=1);

namespace alvin0319\NPC\util;

use Closure;

class Promise{

	public const PENDING = "pending";

	public const REJECTED = "rejected";

	public const FULFILLED = "fulfilled";

	/** @var mixed */
	protected $value = null;

	/** @var string */
	protected $now = self::PENDING;

	/** @var Closure[] */
	protected $fulfilled = [];

	/** @var Closure[] */
	protected $rejected = [];

	public function __construct(){
	}

	public function then(Closure $callback) : Promise{
		if($this->now === self::FULFILLED){
			$callback($this->value);
			return $this;
		}
		$this->fulfilled[] = $callback;
		return $this;
	}

	public function catch(Closure $callback) : Promise{
		if($this->now === self::REJECTED){
			$callback($this->value);
			return $this;
		}
		$this->rejected[] = $callback;
		return $this;
	}

	public function resolve($value) : Promise{
		$this->setNow(self::FULFILLED, $value);
		return $this;
	}

	public function reject($reason) : Promise{
		$this->setNow(self::REJECTED, $reason);
		return $this;
	}

	public function setNow(string $now, $value) : Promise{
		$this->now = $now;
		$this->value = $value;

		$callbacks = $this->now === self::FULFILLED ? $this->fulfilled : $this->rejected;
		foreach($callbacks as $closure){
			$closure($this->value);
		}
		$this->fulfilled = $this->rejected = [];
		return $this;
	}
}
