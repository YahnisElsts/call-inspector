<?php

namespace YahnisElsts\CallInspector\Tests\SampleClasses;

class ClassWithMethods {
	public function publicMethod() {
		echo "Hello from an instance method!\n";
	}

	public static function publicStaticMethod() {
		echo "Hello from a static method!\n";
	}
}