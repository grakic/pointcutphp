<h1>PointcutPHP</h1>

<h3>Intro</h3>
<p>PointcutPHP is yet another approach to introduce some of <a href="http://video.google.com/videoplay?docid=8566923311315412414&q=engEDU">Aspect oriented programming</a> paradigm into the PHP programming language. PointcutPHP is a small object oriented library (single file only) and is using <a href="http://pecl.php.net/package/runkit">PECL/runkit</a> for all hard work. It is inspired by Sebastian Bergmann's <a href="http://github.com/sebastianbergmann/gap">GAP: Generic Aspects for PHP</a>.
</p><p>
Still be warned that PointcutPHP at the moment does not have any unit tests committed to the repository. As it turns out it is not inspired by Sebastian (the author of PHPUnit) that much. The code is published under GNU LGPLv3 or later so you can fix this and commit unit tests or other patches.
</p><br/><p>
With PointcutPHP advices can be free functions, static class methods or object methods. They behave like closures and specified function/method arguments will be imported from inside the join-point scope. 
</p><p>
Supported join-points are function and method calls. Every pointcut is a singleton object and advices can be applied before, after and around join-points using explicit OOP syntax.  </p><p>
Pointcuts expressions including catch all style of join-points are not supported. There is no runtime weaver implemented so this is probably something that will never be available. A recent smart <a href="http://blog.excilys.com/2010/04/30/classes-proxy-en-php-aop/">solution using Proxy class</a> as a runtime weaver is published by Bastien Jansen but it requires that every new object instance should be created through a proxy, and if not advices would not be applied. This risk of unpredictability and the fact that object typehints would not work anymore as we now have only Proxy instances prevents from using this approach in PointcutPHP. At the moment, advices can be applied only on explicatively given method or a function call, one at a time. Chainloading is supported. There is no distinction between static method executions and object method calls and that should be fixed. The bug is evident when object is accessed from the advice in a static context. 
</p><p>
You may contact me by email or <a href="http://facebook.com/grakic">send me a message</a> on Facebook.
</p>
<p>&nbsp;</p>
<h3>Example</h3>
<pre>
class HelloAspect
{
    static public function hello($that, $local = "default")
    {
        echo "Aspect say: Hello $local!\n";
        var_dump($that); /* $that will become $this in the Join-point context */
        return true;
    }
}
MethodCallPointcut::create("Test", "say_moo")
  ->after(new StaticMethodAdvice("HelloAspect", "hello"))
  ->before(new StaticMethodAdvice("HelloAspect", "hello"));
</pre>

<p>First HelloAspect class is defined having HelloAspect::hello() public static method. Then Method call pointcut is created for some Test::say_moo() method call and two advices are applied to it.
</p><p>
PointcutPHP will now rewrite Test::say_moo() so when it gets called all applied advices will get called too. PointcutPHP will pass $this object reference from the join-point target Test instance to the Advice method if $that parameter is specified without any typehint or default value in the advice declaration. Other parameters will be imported from the target scope or defaults will be used.
</p><p>
Return value of before advice will be ignored but return value of after advice will be used instead of target join-point return. There is support for around advices too, where old return value is preserved and used after the advice is executed. 
</p>