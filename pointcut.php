<?php

/**
 * PointcutPHP: Aspect oriented programming for PHP
 *
 * Copyright (c) 2010, Goran Rakic <grakic@devbase.net>
 * 
 * @package    PointcutPHP
 * @author     Goran Rakic <grakic@devbase.net>
 * @license    http://www.gnu.org/licenses/lgpl-3.0-standalone.html  GNU LGPL v3 or later
 */

/**
 * Specify a pointcut singleton where advice can be applied to the joint-point
 */
interface Pointcut
{
    /**
     * Singleton pattern
     */
    static public function create();

    /**
     * Add advice before pointcut preserving old return statement if any
     *
     * @var Advice $f       Advice object
     * @return Pointcut     Return $this
     */
    public function before(Advice $f);

    /**
     * Add advice after pointcut, old return statement will be lost
     *
     * @var Advice $f       Advice object
     * @return Pointcut     Return $this
     */
    public function  after(Advice $f);

    /**
     * Add advice around pointcut, save and reuse return statement
     *
     * @var Advice $f       Advice object
     * @return Pointcut     Return $this
     */ 
    public function around(Advice $f);
}

/**
 * Pointcut singleton for class method call joint-point
 *
 * Implementation uses PECL/runkit to redefine class methods. Note that PECL/runkit description
 * says "For all those things you.... probably shouldn't have been doing anyway". What this class
 * is doing is implementing AOP for PHP in a rather hackish way.
 *
 * When advice is applied to the method call, this pointcut implementation will rename the method
 * name to something like ->name_aop_1() and then build new method that will execute advice and
 * call renamed method. It will also do some clever things so advice can access target object 
 * method and properties and target method local variables.
 */
class MethodCallPointcut implements Pointcut
{
    private $class;
    private $name;

    private $params;
    private $visiblity;
    private $is_static;

    /**
     * One pointcut can have many advices and we need stack counter for overloading target method
     * so each new method can call the previous one and no two names should colide.
     *
     * @var integer
     */
    private $advice_stack_counter;

    /**
     * Singleton instances
     */
    static private $instances = array();

    /**
     * Create new pointcut
     *
     * @var string $class          Class name or '*'
     * @var string $name           Method name or '*'
     * @return MethodCallPointcut  New object instance
     */
    static public function create($class = '*', $name = '*')
    {
        // FIXME: Imeplement!
        if($class == '*' || $name == '*')
            throw new Exception('Not implemented');

        $sign = $class.'::'.$name;
        if(array_key_exists($sign, self::$instances)) {
            return self::$instances[$sign];
        }
        else {
            $c = __CLASS__;
            $pointcut = new $c($class, $name);
            self::$instances[$sign] = $pointcut;
            return $pointcut;
        }
    }

    private function __construct($class, $name)
    {
        $this->class = $class;
        $this->name  = $name;

        // Get some info on this method and cache them inside singleton
        // so we do not have to call reflection later
        $method = new ReflectionMethod($this->class, $name);

        $this->params = implode_parameters(get_parameters($method));

        $this->is_static = $method->isStatic();

        if($method->isPrivate())
            $this->visibility = RUNKIT_ACC_PRIVATE;
        elseif($method->isProtected())
            $this->visibility = RUNKIT_ACC_PROTECTED;
        else
            $this->visibility = RUNKIT_ACC_PUBLIC;

        // Initialize stack counter for overloaded method name
        $this->advice_stack_counter = 0;
    }

    /**
     * Get PHP code as string to invoke this joint-point
     */
    private function get_invoke_code($name)
    {
        if($this->is_static) {
            return 'self::'.$name.'('.$this->params.')';
        }
        else {
            return '$this->'.$name.'('.$this->params.')';
        }
    }

    /**
     * Rename current join-point method and increment stack counter
     *
     * @return string  New join-point method name
     */
    private function rename()
    {
        $new_name = $this->name.'_aop_'.$this->advice_stack_counter;
        runkit_method_rename($this->class, $this->name, $new_name);
        $this->advice_stack_counter++;

        return $new_name;
    }

    /**
     * Redefine join-point method using given PHP code. Old mehtod should be renamed before
     * or it will be lost. Uses PECL/runkit for all hard work.
     *
     * @var string $code  Method code
     * @return bull       true on success, false on failure
     */
    private function redefine($code)
    {
        return runkit_method_add($this->class, $this->name, $this->params, $code, $this->visibility);
    }

    /**
     * Call Advice, then return join-point method
     *
     * @var Advice $f              Advice object
     * @return MethodCallPointcut  Return $this
     */
    public function before(Advice $f)
    {
        $new_name = $this->rename();
        $code = $f->get_invoke_code().'; return '.$this->get_invoke_code($new_name).';';
        $this->redefine($code);

        return $this;
    }

    /**
     * Call joint-point method, then return advice
     *
     * @var Advice $f              Advice object
     * @return MethodCallPointcut  Return $this
     */
    public function after(Advice $f)
    {
        $new_name = $this->rename();
        $code = $this->get_invoke_code($new_name).'; return '.$f->get_invoke_code().';';
        $this->redefine($code);

        return $this;
    }

    /**
     * Call joint-point method and save return value, call advice and then return saved value
     *
     * @var Advice $f              Advice object
     * @return MethodCallPointcut  Return $this
     */
    public function around(Advice $f)
    {
        $new_name = $this->rename();
        $code = '$ret = '.$this->get_invoke_code($new_name).'; '.$f->get_invoke_code().' return $ret;';
        $this->redefine($code);

        return $this;
    }
}

/**
 * Free function to take array returned by get_parameters and return imploded string of PHP code
 *
 * @var Array $parameters  Return of get_parameters()
 * @return string          PHP code
 */
function implode_parameters($parameters)
{
    $params = ''; $sep = '';
    foreach($parameters as $name => $data)
    {
        $params .= $sep.$data['hint'].'$'.$name.$data['value'];
        $sep = ', ';
    }

    return $params;
}

/**
 * Free function to get parameters of a reflected function or method as array of strings
 *
 * @var ReflectionFunctionAbstract $method  Reflection object
 * @return Array[name=>hint,value]          Parameters array
 */
function get_parameters(ReflectionFunctionAbstract $method)
{
    $parameters = array();

    foreach($method->getParameters() as $param)
    {
        $name = $param->getName();

        // typehint and reference prefix if any
        $hint = '';
        if($param->isArray())             $hint = 'Array ';
        elseif($c = $param->getClass())   $hint = $c;

        if($param->isPassedByReference()) $hint .= ' &';

        // default value
        $value = '';
        if ($param->isDefaultValueAvailable()) {
            $value = '='.var_export($param->getDefaultValue(), true);
        }

        $parameters[$name] = array('hint'=>$hint, 'value'=>$value);
    }

    return $parameters;
}

/**
 * Advice that can be applied to the pointcut
 */
interface Advice
{
    /**
     * Returns Advice invoke code as PHP string
     *
     * @return string
     */
    public function get_invoke_code();
}

/**
 * Function advice
 */
class FunctionAdvice implements Advice
{
    private $name;

    private $invoke_code = null;

    /**
     * Create new function advice
     *
     * @var string $name  Function name
     */
    public function __construct($name)
    {
        $this->name = $name;
    }

    public function get_invoke_code()
    {
        if(is_null($this->invoke_code)) $this->invoke_code = $this->_get_invoke_code();

        return $this->invoke_code;
    }

    protected function _get_parameters_code($method)
    {
        /*
         * Hack parameters and map any $that without value and hing to $this
         */
        $params = get_parameters($method);
        if(array_key_exists('that', $params) && $params['that']['hint'] == '' && $params['that']['value'] == '') {
            $params['that']['value'] = '=$this';
        }

        return implode_parameters($params);
    }

    protected function _get_invoke_code()
    {
        $method = new ReflectionFunction($this->name);

        return $this->name.'('.$this->_get_parameters_code($method).')';
    }
}

/**
 * Public static method advice
 */
class StaticMethodAdvice extends FunctionAdvice implements Advice
{
    private $class;
    private $name;

    public function __construct($class, $name)
    {
        $this->class = $class;
        $this->name  = $name;
    }

    protected function _get_invoke_code()
    {
        $method = new ReflectionMethod($this->class, $this->name);

        return $this->class.'::'.$this->name.'('.$this->_get_parameters_code($method).')';
    }
}

/**
 * Public class method advice
 */
class ObjectMethodAdvice extends FunctionAdvice implements Advice
{
    private $object;
    private $name;
    private $hash;

    /**
     * Object registry
     */
    static private $registry = array();

    public function __construct($object, $name)
    {
        $this->object = $object;
        $this->name   = $name;
        $this->hash   = get_class($object).'_'.uniqid();

        self::$registry[$this->hash] = $object;
    }

    /**
     * Registry pattern for advice objects so method could be invoked properly from the join-point
     */
    static public function get_object($hash)
    {
        return self::$registry[$hash];
    }

    protected function _get_invoke_code()
    {
        $method = new ReflectionMethod(get_class($this->object), $this->name);

        return 'ObjectMethodAdvice::get_object("'.$this->hash.'")->'.$this->name.'('.$this->_get_parameters_code($method).')';
    }
}

?>
