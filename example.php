<?php

require_once('pointcut.php');

class TestStaticAspect
{
    static public function hello($that, $local = "default")
    {
        echo "Aspect say: Hello $local!\n";
        var_dump($that); /* $that will become $this in the join-point context */
    }
}
MethodCallPointcut::create("Test", "say_moo")
  ->after(new StaticMethodAdvice("TestStaticAspect", "hello"))
  ->before(new StaticMethodAdvice("TestStaticAspect", "hello"));

class TestObjectAspect
{
    private $x;

    public function __construct($x)
    {
        $this->x = $x;
    }

    public function hello($that)
    {
        echo "Test say hi: ".$this->x."\n";
        var_dump($that); /* $that will become $this in the join-point context */
    }
}

$test = new TestObjectAspect(5);
MethodCallPointcut::create("Test", "say_moo")
  ->after(new ObjectMethodAdvice($test, "hello"));


/**
 * ---------------------------------------------
 */

class Test
{
    public $hello = 'Hello';

    public function __construct()
    {
        echo "Test constructor!\n";
    }

    public function say_moo()
    {
        $local = 'World';
        echo "Test say moo!\n";
    }
}

$t = new Test();
$t->say_moo();

?>
