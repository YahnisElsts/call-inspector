<?php

namespace YahnisElsts\CallInspector\v1p1;

use Closure;
use InvalidArgumentException;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;

class InspectableCallable {
	/**
	 * @var callable
	 */
	private $callable;

	private bool $triedReflection = false;
	/**
	 * @var null|\ReflectionFunctionAbstract
	 */
	private $cachedReflection = null;

	/**
	 * @param callable $underlyingCallable
	 */
	public function __construct(callable $underlyingCallable) {
		$this->callable = $underlyingCallable;
	}

	/**
	 * @param mixed $callable A callable value.
	 * @return static
	 * @throws \InvalidArgumentException If the provided argument is not callable.
	 */
	static function fromCallable($callable): InspectableCallable {
		if ( !is_callable($callable) ) {
			throw new InvalidArgumentException('The provided argument is not callable.');
		}

		return new static($callable);
	}

	/**
	 * Get a formatted name for this callable.
	 *
	 * Examples:
	 * - "foo" for a function named "foo".
	 * - "Foo::bar" for a static method "bar" in class "Foo".
	 * - "Foo->bar" for an instance method "bar" in class "Foo".
	 *
	 * @return string
	 */
	public function formatName(): string {
		if ( is_string($this->callable) ) {
			return $this->callable;
		}

		if ( is_array($this->callable) ) {
			if ( is_object($this->callable[0]) ) {
				return get_class($this->callable[0]) . '->' . $this->callable[1];
			} else {
				return $this->callable[0] . '::' . $this->callable[1];
			}
		}

		if ( $this->callable instanceof Closure ) {
			return '{closure}';
		}

		if ( is_object($this->callable) ) {
			/** @noinspection PhpParamsInspection
			 * The is_object() check above already ensures that the callable is an object.
			 */
			return get_class($this->callable);
		}

		return 'unknown';
	}

	/**
	 * Get the full path to the file where the callable is defined.
	 *
	 * @return string The file name, or an empty string if the callable is not defined in a file
	 *                (e.g. a built-in function).
	 */
	public function getFileName(): string {
		$reflection = $this->getReflection();
		if ( $reflection === null ) {
			return '';
		}

		$path = $reflection->getFileName();
		if ( !is_string($path) ) {
			return '';
		}

		//Normalize directory separators.
		return str_replace('\\', '/', $path);
	}

	/**
	 * Get the line number where the callable is defined.
	 *
	 * @return int The line number, or 0 if the callable is not defined in a file
	 *             or the line number cannot be determined.
	 */
	public function getStartLine(): int {
		$reflection = $this->getReflection();
		if ( $reflection === null ) {
			return 0;
		}

		$line = $reflection->getStartLine();
		if ( !is_int($line) ) {
			return 0;
		}
		return $line;
	}

	public function getReflection() {
		if ( $this->triedReflection ) {
			return $this->cachedReflection;
		}

		$this->triedReflection = true;
		$this->cachedReflection = null;

		try {
			if ( is_object($this->callable) ) {
				//Is this a closure?
				if ( $this->callable instanceof Closure ) {
					$this->cachedReflection = new ReflectionFunction($this->callable);
					//Is this an invokable object?
				} else if ( method_exists($this->callable, '__invoke') ) {
					$this->cachedReflection = new ReflectionMethod($this->callable, '__invoke');
				} else {
					//This object doesn't appear to be callable at all!
					$this->cachedReflection = null;
				}
			} else if ( is_array($this->callable) ) {
				$this->cachedReflection = new ReflectionMethod($this->callable[0], $this->callable[1]);
			} else if ( is_string($this->callable) ) {
				//Is this a function?
				if ( function_exists($this->callable) ) {
					$this->cachedReflection = new ReflectionFunction($this->callable);
				} else {
					//Is this a static method?
					if ( strpos($this->callable, '::') !== false ) {
						$parts = explode('::', $this->callable);
						$this->cachedReflection = new ReflectionMethod($parts[0], $parts[1]);
					}
				}
			}
		} catch (ReflectionException $e) {
			//Eat the exception.
			$this->cachedReflection = null;
			return null;
		}

		return $this->cachedReflection;
	}

	/**
	 * Get the file name and line number of the callable, formatted as "/path/to/filename.php:123".
	 *
	 * @return string The file name and line number, or an empty string.
	 */
	public function getFileNameAndLineNumber(): string {
		$fileName = $this->getFileName();
		$lineNumber = $this->getStartLine();

		if ( ($fileName === '') || ($lineNumber === 0) ) {
			return '';
		}

		return $fileName . ':' . $lineNumber;
	}
}