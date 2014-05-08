<?php
namespace Corvo\Tools\Decorators;

use \BadMethodCallException;

/**
 * Decorator class Chain
 *
 * As long as "setter" methods are called (as in: no return value) then $this is returned.
 * This allows method-chaining on an otherwise unchainable object.
 *
 * Only downside is that methods that intentionally return NULL are also replaced by $this.
 * Chaining can be disabled() until enabled() or chaining can be circumvented once by doing a raw() call.
 *
 * <code>
 * // The returned object from link() can be stored or immediately used for chaining
 * Chain::link(new Foo())->setFooPropX(42)->output();
 * </code>
 *
 * <code>
 * // When using reaction() all returned objects are decorated with a Chain object.
 * Chain::reaction(new Foo())->getBarProp()->setBarPropX(37)->write();
 * </code>
 *
 * @author    Francois Raeven <francois@raeven.eu>
 * @link      http://raeven.eu/opensource
 * @license   http://opensource.org/licenses/MIT MIT License
 * @copyright (c) 2014, Francois Raeven
 */
class Chain
{
    /**
     * If disabled returned NULL values will not be replaced with $this.
     * This effectively disables all "decoration" until enabled again.
     *
     * @var boolean
     */
    protected $enabled = true;

    /**
     * If enabled all return values containing an object will automatically decorate the object with the Chain class.
     *
     * @var boolean
     */
    protected $propagate;

    /**
     * Used to store method names which will always be called "raw" (no decoration) and which will never automatically
     * decorate a returned object with the Chain class.
     *
     * @var string[]
     */
    protected $whitelist = array();

    /**
     * The object being decorated with the Method Chaining behaviour.
     */
    protected $wrappedObj;

    /**
     * Creates an instance of the Chain class decorating the specified object.
     * If a method from the decorated object returns an object, it is returned as-is.
     * In this case the object is considered to be non-propagating.
     *
     * @author Francois Raeven <francois@raeven.eu>
     *
     * @param $wrappedObj
     *
     * @return Chain
     */
    public static function link($wrappedObj)
    {
        return new self($wrappedObj, false);
    }

    /**
     * Creates an instance of the Chain class decorating the specified object.
     * If a method from the decorated object returns an object, this object is also wrapped in a new Chain instance.
     * In this case the object is considered to be propagating.
     *
     * @author Francois Raeven <francois@raeven.eu>
     *
     * @param $wrappedObj
     *
     * @return Chain
     */
    public static function reaction($wrappedObj)
    {
        return new self($wrappedObj, true);
    }

    /**
     * Constructor
     *
     * @author Francois Raeven <francois@raeven.eu>
     *
     * @param         $wrappedObj
     * @param boolean $isPropagated
     */
    protected function __construct($wrappedObj, $isPropagated)
    {
        $this->wrappedObj = $wrappedObj;
        $this->propagate  = $isPropagated;
    }

    /**
     * Perform a method call to the specified method name using the specified arguments array.
     *
     * @author Francois Raeven <francois@raeven.eu>
     *
     * @param string $method
     * @param array  $args
     *
     * @return mixed|null
     * @throws \BadMethodCallException
     */
    public function raw($method, array $args = array())
    {
        $result = null;

        if (method_exists($this->wrappedObj, $method) && is_callable(array($this->wrappedObj, $method))) {
            $result = call_user_func_array(array($this->wrappedObj, $method), $args);
        } else {
            throw new BadMethodCallException(sprintf('Call to undefined method %s::%s()', get_class($this->wrappedObj), $method));
        }

        return $result;
    }

    /**
     * Call wrapped object method.
     *
     * If the called method returns NULL then it will return $this (Chain instance) instead, unless:
     * - the method is whitelisted
     * - the chain object is disabled
     *
     * If propagation is enabled then an object is decorated by the Chain class as well.
     *
     * @author Francois Raeven <francois@raeven.eu>
     *
     * @param string $method
     * @param array  $args
     *
     * @return $this|mixed|null
     */
    public function __call($method, array $args)
    {
        $result = $this->raw($method, $args);

        // If disabled or whitelisted then return as-is
        if (false === $this->enabled || array_key_exists($method, $this->whitelist)) {
            return $result;
        }

        // If the wrapped object itself is returned then the decorator class will be returned instead.
        if ($result === $this->wrappedObj) {
            return $this;
        }

        // If propagating then objects are decorated with Chain as well.
        if ($this->propagate && is_object($result)) {
            return Chain::reaction($result);
        }

        // Last but not least: if NULL then return $this instead.
        if (null === $result) {
            return $this;
        }

        // ...otherwise just return value as-is.
        return $result;
    }

    /**
     * Set wrapped object property.
     *
     * @author Francois Raeven <francois@raeven.eu>
     *
     * @param string $prop
     * @param mixed  $value
     *
     * @return $this
     */
    public function __set($prop, $value)
    {
        $this->wrappedObj->$prop = $value;

        return $this;
    }

    /**
     * Get wrapped object property.
     *
     * @author Francois Raeven <francois@raeven.eu>
     *
     * @param string $prop
     *
     * @return mixed
     */
    public function __get($prop)
    {
        return $this->wrappedObj->$prop;
    }

    /**
     * Check if wrapped object property is set.
     *
     * @author Francois Raeven <francois@raeven.eu>
     *
     * @param string $prop
     *
     * @return boolean
     */
    public function __isset($prop)
    {
        return isset($this->wrappedObj->$prop);
    }

    /**
     * Unset wrapped object property.
     *
     * @author Francois Raeven <francois@raeven.eu>
     *
     * @param string $prop
     */
    public function __unset($prop)
    {
        unset($this->wrappedObj->$prop);
    }

    /**
     * Disable the Chain behaviour when calling wrapped object methods.
     *
     * @author Francois Raeven <francois@raeven.eu>
     *
     * @return $this
     */
    public function disable()
    {
        $this->enabled = false;

        return $this;
    }

    /**
     * Enable the Chain behaviour when calling wrapped object methods.
     *
     * @author Francois Raeven <francois@raeven.eu>
     *
     * @return $this
     */
    public function enable()
    {
        $this->enabled = true;

        return $this;
    }

    /**
     * Register method for whitelisting.
     *
     * @author Francois Raeven <francois@raeven.eu>
     *
     * @param string $method
     *
     * @return $this
     */
    public function whitelist($method)
    {
        if (!array_key_exists($method, $this->whitelist)) {
            $this->whitelist[$method] = $method;
        }

        return $this;
    }

    /**
     * Unregister method from whitelist.
     *
     * @author Francois Raeven <francois@raeven.eu>
     *
     * @param string $method
     *
     * @return $this
     */
    public function unwhitelist($method)
    {
        if (array_key_exists($method, $this->whitelist)) {
            unset($this->whitelist[$method]);
        }

        return $this;
    }

    /**
     * Retrieve all whitelist contents.
     *
     * @author Francois Raeven <francois@raeven.eu>
     *
     * @return string[]
     */
    public function getWhitelist()
    {
        return $this->whitelist;
    }

    /**
     * Replace whitelist with the specified array.
     *
     * @author Francois Raeven <francois@raeven.eu>
     *
     * @param array $whitelist
     *
     * @return $this
     */
    public function setWhitelist(array $whitelist)
    {
        $this->whitelist = $whitelist;

        return $this;
    }
}
