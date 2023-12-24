<?php
/** @noinspection PhpParamsInspection
 *
 * PHPUnit needs PHP 8, but this library as a whole supports PHP 7.4, so the parameter
 * inspections are messed up in tests.
 */

namespace YahnisElsts\CallInspector\Tests;

use DateTime;
use PHPUnit\Framework\TestCase;
use YahnisElsts\CallInspector\InspectableCallable;
use YahnisElsts\CallInspector\Tests\SampleClasses\ClassWithMethods;

class InspectableCallableTest extends TestCase {
	public function testFormatName() {
		//Simple function name.
		$callable = 'strlen';
		$inspectable = InspectableCallable::fromCallable($callable);
		$this->assertEquals($callable, $inspectable->formatName());

		//Static class method, 2 element array.
		$callable = ['DateTime', 'createFromFormat'];
		$inspectable = InspectableCallable::fromCallable($callable);
		$this->assertEquals('DateTime::createFromFormat', $inspectable->formatName());

		//Static class method, but as a string.
		$callable = 'DateTime::createFromFormat';
		$inspectable = InspectableCallable::fromCallable($callable);
		$this->assertEquals($callable, $inspectable->formatName());

		//Instance method.
		$callable = [new DateTime(), 'format'];
		$inspectable = InspectableCallable::fromCallable($callable);
		$this->assertEquals('DateTime->format', $inspectable->formatName());

		//Anonymous function (closure).
		$callable = function () {
			return 'Hello, world!';
		};
		$inspectable = InspectableCallable::fromCallable($callable);
		$this->assertEquals('{closure}', $inspectable->formatName());

		//Invokable object.
		$invokable = new class {
			public function __invoke(): string {
				return 'Hello, world!';
			}
		};
		$inspectable = InspectableCallable::fromCallable($invokable);
		$this->assertEquals(get_class($invokable), $inspectable->formatName());
	}

	public function testNamespacedClassFormatting() {
		$callable = [new ClassWithMethods(), 'publicMethod'];
		$inspectable = InspectableCallable::fromCallable($callable);
		$this->assertEquals(
			'YahnisElsts\CallInspector\Tests\SampleClasses\ClassWithMethods->publicMethod',
			$inspectable->formatName()
		);

		$callable = [ClassWithMethods::class, 'publicStaticMethod'];
		$inspectable = InspectableCallable::fromCallable($callable);
		$this->assertEquals(
			'YahnisElsts\CallInspector\Tests\SampleClasses\ClassWithMethods::publicStaticMethod',
			$inspectable->formatName()
		);
	}

	public function testFileNameAndLineNumber() {
		//Class method.
		$callable = [new ClassWithMethods(), 'publicMethod'];
		$inspectable = InspectableCallable::fromCallable($callable);

		$this->assertEquals(
			self::normalizePath(__DIR__ . '/SampleClasses/ClassWithMethods.php'),
			self::normalizePath($inspectable->getFileName())
		);
		$this->assertEquals(6, $inspectable->getStartLine());

		$this->assertEquals(
			self::normalizePath(__DIR__ . '/SampleClasses/ClassWithMethods.php:6'),
			self::normalizePath($inspectable->getFileNameAndLineNumber())
		);

		//Static class method.
		$callable = [ClassWithMethods::class, 'publicStaticMethod'];
		$inspectable = InspectableCallable::fromCallable($callable);
		$this->assertEquals(
			self::normalizePath(__DIR__ . '/SampleClasses/ClassWithMethods.php:10'),
			self::normalizePath($inspectable->getFileNameAndLineNumber())
		);

		//Built-in function.
		$callable = 'strlen';
		$inspectable = InspectableCallable::fromCallable($callable);
		$this->assertEquals('', $inspectable->getFileName());
		$this->assertEquals(0, $inspectable->getStartLine());
		$this->assertEquals('', $inspectable->getFileNameAndLineNumber());

		//Anonymous function (closure).
		$callable = function () {
			return 'Hello, world!';
		};
		$inspectable = InspectableCallable::fromCallable($callable);
		$this->assertEquals(
			self::normalizePath(__FILE__),
			$inspectable->getFileName()
		);
		//The line number could change if we add/edit tests, so we don't check
		//for a specific value here, just that it's not 0.
		$this->assertNotEquals(0, $inspectable->getStartLine());
	}

	private static function normalizePath($path) {
		return str_replace('\\', '/', $path);
	}
}