<?php
/**
 * Part of the Joomla Framework Registry Package
 *
 * @copyright  Copyright (C) 2005 - 2016 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\Registry;

use Joomla\Utilities\ArrayHelper;

/**
 * Registry class
 *
 * @since  1.0
 */
class Registry implements \JsonSerializable, \ArrayAccess, \IteratorAggregate, \Countable
{
	/**
	 * Registry Object
	 *
	 * @var    \stdClass
	 * @since  1.0
	 */
	protected $data;

	/**
	 * Flag if the Registry data object has been initialized
	 *
	 * @var    boolean
	 * @since  1.5.2
	 */
	protected $initialized = false;

	/**
	 * Path separator
	 *
	 * @var    string
	 * @since  1.4.0
	 */
	public $separator = '.';

	/**
	 * Constructor
	 *
	 * @param   mixed  $data  The data to bind to the new Registry object.
	 *
	 * @since   1.0
	 */
	public function __construct($data = null)
	{
		// Instantiate the internal data object.
		$this->data = new \stdClass;

		// Optionally load supplied data.
		if ($data !== null)
		{
			if ($data instanceof Registry)
			{
				$this->merge($data);
			}
			elseif (is_array($data) || is_object($data))
			{
				$this->bindData($this->data, $data);
			}
			elseif (is_string($data) && $data !== '')
			{
				$this->loadString($data);
			}
		}
	}

	/**
	 * Magic function to clone the registry object.
	 *
	 * @return  void
	 *
	 * @since   1.0
	 */
	public function __clone()
	{
		$this->data = unserialize(serialize($this->data));
	}

	/**
	 * Magic function to render this object as a string using default args of toString method.
	 *
	 * @return  string
	 *
	 * @since   1.0
	 */
	public function __toString()
	{
		return $this->toString();
	}

	/**
	 * Count elements of the data object
	 *
	 * @return  integer  The custom count as an integer.
	 *
	 * @link    https://secure.php.net/manual/en/countable.count.php
	 * @since   1.3.0
	 */
	public function count()
	{
		return count(get_object_vars($this->data));
	}

	/**
	 * Implementation for the JsonSerializable interface.
	 * Allows us to pass Registry objects to json_encode.
	 *
	 * @return  object
	 *
	 * @since   1.0
	 * @note    The interface is only present in PHP 5.4 and up.
	 */
	public function jsonSerialize()
	{
		return $this->data;
	}

	/**
	 * Sets a default value if not already assigned.
	 *
	 * @param   string  $key      The name of the parameter.
	 * @param   mixed   $default  An optional value for the parameter.
	 *
	 * @return  mixed  The value set, or the default if the value was not previously set (or null).
	 *
	 * @since   1.0
	 */
	public function def($key, $default = '')
	{
		$value = $this->get($key, $default);
		$this->set($key, $value);

		return $value;
	}

	/**
	 * Check if a registry path exists.
	 *
	 * @param   string  $path  Registry path (e.g. joomla.content.showauthor)
	 *
	 * @return  boolean
	 *
	 * @since   1.0
	 */
	public function exists($path)
	{
		try
		{
			// Get the path node value.
			$this->getNodeValue($path);

			return true;
		}
		catch (Exception $e)
		{
			return false;
		}
	}

	/**
	 * Get a registry value.
	 *
	 * @param   string  $path     Registry path (e.g. joomla.content.showauthor)
	 * @param   mixed   $default  Optional default value, returned if the internal value is null.
	 *
	 * @return  mixed  Value of entry or null
	 *
	 * @since   1.0
	 */
	public function get($path, $default = null)
	{
		try
		{
			// Get the path node value.
			$nodeValue = $this->getNodeValue($path);
		}
		catch (Exception $e)
		{
			return $default;
		}

		return $nodeValue === null || $nodeValue === '' ? $default : $nodeValue;
	}

	/**
	 * Gets this object represented as an ArrayIterator.
	 *
	 * This allows the data properties to be accessed via a foreach statement.
	 *
	 * @return  \ArrayIterator  This object represented as an ArrayIterator.
	 *
	 * @see     IteratorAggregate::getIterator()
	 * @since   1.3.0
	 */
	public function getIterator()
	{
		return new \ArrayIterator($this->data);
	}

	/**
	 * Load an associative array of values into the default namespace
	 *
	 * @param   array    $array      Associative array of value to load
	 * @param   boolean  $flattened  Load from a one-dimensional array
	 * @param   string   $separator  The key separator
	 *
	 * @return  Registry  Return this object to support chaining.
	 *
	 * @since   1.0
	 */
	public function loadArray(array $array, $flattened = false, $separator = null)
	{
		if ($flattened === false)
		{
			$this->bindData($this->data, $array);

			return $this;
		}

		foreach ($array as $k => $v)
		{
			$this->set($k, $v, $separator);
		}

		return $this;
	}

	/**
	 * Load the public variables of the object into the default namespace.
	 *
	 * @param   object  $object  The object holding the publics to load
	 *
	 * @return  Registry  Return this object to support chaining.
	 *
	 * @since   1.0
	 */
	public function loadObject($object)
	{
		$this->bindData($this->data, $object);

		return $this;
	}

	/**
	 * Load the contents of a file into the registry
	 *
	 * @param   string  $file     Path to file to load
	 * @param   string  $format   Format of the file [optional: defaults to JSON]
	 * @param   array   $options  Options used by the formatter
	 *
	 * @return  Registry  Return this object to support chaining.
	 *
	 * @since   1.0
	 */
	public function loadFile($file, $format = 'JSON', array $options = [])
	{
		return $this->loadString(file_get_contents($file), $format, $options);
	}

	/**
	 * Load a string into the registry
	 *
	 * @param   string  $data     String to load into the registry
	 * @param   string  $format   Format of the string
	 * @param   array   $options  Options used by the formatter
	 *
	 * @return  Registry  Return this object to support chaining.
	 *
	 * @since   1.0
	 */
	public function loadString($data, $format = 'JSON', array $options = [])
	{
		// Load a string into the given namespace [or default namespace if not given]
		$obj = Factory::getFormat($format, $options)->stringToObject($data, $options);

		// If the data object has not yet been initialized, direct assign the object
		if ($this->initialized === false)
		{
			$this->data        = $obj;
			$this->initialized = true;

			return $this;
		}

		$this->loadObject($obj);

		return $this;
	}

	/**
	 * Merge a Registry object into this one
	 *
	 * @param   Registry  $source     Source Registry object to merge.
	 * @param   boolean   $recursive  True to support recursive merge the children values.
	 *
	 * @return  Registry  Return this object to support chaining.
	 *
	 * @since   1.0
	 */
	public function merge(Registry $source, $recursive = false)
	{
		$this->bindData($this->data, $source->toArray(), $recursive, false);

		return $this;
	}

	/**
	 * Method to extract a sub-registry from path
	 *
	 * @param   string  $path  Registry path (e.g. joomla.content.showauthor)
	 *
	 * @return  Registry|null  Registry object if data is present
	 *
	 * @since   1.2.0
	 */
	public function extract($path)
	{
		$data = $this->get($path);

		if (null === $data)
		{
			return null;
		}

		return new Registry($data);
	}

	/**
	 * Checks whether an offset exists in the iterator.
	 *
	 * @param   mixed  $offset  The array offset.
	 *
	 * @return  boolean  True if the offset exists, false otherwise.
	 *
	 * @since   1.0
	 */
	public function offsetExists($offset)
	{
		return $this->exists($offset);
	}

	/**
	 * Gets an offset in the iterator.
	 *
	 * @param   mixed  $offset  The array offset.
	 *
	 * @return  mixed  The array value if it exists, null otherwise.
	 *
	 * @since   1.0
	 */
	public function offsetGet($offset)
	{
		return $this->get($offset);
	}

	/**
	 * Sets an offset in the iterator.
	 *
	 * @param   mixed  $offset  The array offset.
	 * @param   mixed  $value   The array value.
	 *
	 * @return  void
	 *
	 * @since   1.0
	 */
	public function offsetSet($offset, $value)
	{
		$this->set($offset, $value);
	}

	/**
	 * Unsets an offset in the iterator.
	 *
	 * @param   mixed  $offset  The array offset.
	 *
	 * @return  void
	 *
	 * @since   1.0
	 */
	public function offsetUnset($offset)
	{
		$this->set($offset, null);
	}

	/**
	 * Set a registry value.
	 *
	 * @param   string  $path       Registry Path (e.g. joomla.content.showauthor)
	 * @param   mixed   $value      Value of entry
	 * @param   string  $separator  The key separator
	 *
	 * @return  mixed  The value of the that has been set.
	 *
	 * @since   1.0
	 */
	public function set($path, $value, $separator = null)
	{
		// Get the path nodes.
		$pathNodes = $this->getPathNodes($path, $separator);

		if ($pathNodes === [])
		{
			return null;
		}

		// Initialize the current node to be the registry root.
		$node = $this->data;

		// Traverse the registry to find the correct node for the result.
		foreach ($i = 0, $n = count($pathNodes) - 1; $i < $n; $i++)
		{
			if (is_object($node))
			{
				if ($i !== $n && !isset($node->{$pathNodes[$i]}))
				{
					$node->{$pathNodes[$i]} = new \stdClass;
				}

				// Pass the child as pointer in case it is an object
				$node = &$node->{$pathNodes[$i]};
			}
			elseif (is_array($node))
			{
				if ($i !== $n && !isset($node[$pathNodes[$i]]))
				{
					$node[$pathNodes[$i]] = new \stdClass;
				}

				// Pass the child as pointer in case it is an array
				$node = &$node[$pathNodes[$i]];
			}
		}

		// Get the old value if exists so we can return it
		if (is_object($node))
		{
			return $node->{$pathNodes[$i]} = $value;
		}

		if (is_array($node))
		{
			return $node[$pathNodes[$i]] = $value;
		}

		return null;
	}

	/**
	 * Append value to a path in registry
	 *
	 * @param   string  $path   Parent registry Path (e.g. joomla.content.showauthor)
	 * @param   mixed   $value  Value of entry
	 *
	 * @return  mixed  The value of the that has been set.
	 *
	 * @since   1.4.0
	 */
	public function append($path, $value)
	{
		// Get the current value.
		$currentValue = $this->get($path, null);

		if ($currentValue === null)
		{
			return $this->set($path, $value);
		}

		// Convert the node to array to make append possible
		if (is_array($currentValue) === false)
		{
			$currentValue = get_object_vars($currentValue);
		}

		$currentValue[] = $value;

		return $this->set($path, $currentValue);
	}

	/**
	 * Transforms a namespace to an array
	 *
	 * @return  array  An associative array holding the namespace data
	 *
	 * @since   1.0
	 */
	public function toArray()
	{
		return (array) $this->asArray($this->data);
	}

	/**
	 * Transforms a namespace to an object
	 *
	 * @return  object   An an object holding the namespace data
	 *
	 * @since   1.0
	 */
	public function toObject()
	{
		return $this->data;
	}

	/**
	 * Get a namespace in a given string format
	 *
	 * @param   string  $format   Format to return the string in
	 * @param   array   $options  Parameters used by the formatter, see formatters for more info
	 *
	 * @return  string   Namespace in string format
	 *
	 * @since   1.0
	 */
	public function toString($format = 'JSON', array $options = [])
	{
		return Factory::getFormat($format, $options)->objectToString($this->data, $options);
	}

	/**
	 * Method to recursively bind data to a parent object.
	 *
	 * @param   object   $parent     The parent object on which to attach the data values.
	 * @param   mixed    $data       An array or object of data to bind to the parent object.
	 * @param   boolean  $recursive  True to support recursive bindData.
	 * @param   boolean  $allowNull  True to allow null values.
	 *
	 * @return  void
	 *
	 * @since   1.0
	 */
	protected function bindData($parent, $data, $recursive = true, $allowNull = true)
	{
		// The data object is now initialized
		$this->initialized = true;

		// Ensure the input data is an array.
		$data = is_object($data) ? get_object_vars($data) : (array) $data;

		foreach ($data as $k => $v)
		{
			if ($allowNull === false && ($v === null || $v === ''))
			{
				continue;
			}

			if ($recursive === true && (is_object($v) || (is_array($v) && ArrayHelper::isAssociative($v))))
			{
				if (!isset($parent->$k))
				{
					$parent->$k = new \stdClass;
				}

				$this->bindData($parent->$k, $v);

				continue;
			}

			$parent->$k = $v;
		}
	}

	/**
	 * Method to recursively convert an object of data to an array.
	 *
	 * @param   object  $data  An object of data to return as an array.
	 *
	 * @return  array  Array representation of the input object.
	 *
	 * @since   1.0
	 */
	protected function asArray($data)
	{
		$array = [];

		if (is_object($data))
		{
			$data = get_object_vars($data);
		}

		foreach ($data as $k => $v)
		{
			if (is_object($v) || is_array($v))
			{
				$array[$k] = $this->asArray($v);

				continue;
			}

			$array[$k] = $v;
		}

		return $array;
	}

	/**
	 * Dump to one dimension array.
	 *
	 * @param   string  $separator  The key separator.
	 *
	 * @return  string[]  Dumped array.
	 *
	 * @since   1.3.0
	 */
	public function flatten($separator = null)
	{
		$array = [];

		$this->toFlatten($this->getSeparator($separator), $this->data, $array);

		return $array;
	}

	/**
	 * Method to recursively convert data to one dimension array.
	 *
	 * @param   string        $separator  The key separator.
	 * @param   array|object  $data       Data source of this scope.
	 * @param   array         &$array     The result array, it is pass by reference.
	 * @param   string        $prefix     Last level key prefix.
	 *
	 * @return  void
	 *
	 * @since   1.3.0
	 */
	protected function toFlatten($separator = null, $data = null, array &$array = [], $prefix = '')
	{
		$data      = (array) $data;
		$separator = $this->getSeparator($separator);

		foreach ($data as $k => $v)
		{
			$key = $prefix !== null && $prefix !== '' ? $prefix . $separator . $k : $k;

			if (is_object($v) || is_array($v))
			{
				$this->toFlatten($separator, $v, $array, $key);

				continue;
			}

			$array[$key] = $v;
		}
	}

	/**
	 * Method to get the separator.
	 *
	 * @param   string  $separator  The key separator
	 *
	 * @return  string  The separator.
	 *
	 * @since   2.0.0
	 */
	private function getSeparator($separator = null)
	{
		if ($separator === null || $separator === '')
		{
			return $this->separator;
		}

		return $separator;
	}

	/**
	 * Method to get the path nodes.
	 * Explode the registry path into an array and remove empty
	 * nodes that occur as a result of a double dot. ex: joomla..test
	 * Finally, re-key the array so they are sequential.
	 *
	 * @param   string  $path       Registry Path (e.g. joomla.content.showauthor)
	 * @param   string  $separator  The key separator
	 *
	 * @return  array  The path nodes.
	 *
	 * @since   2.0.0
	 */
	private function getPathNodes($path = '', $separator = null)
	{
		$path = trim((string) $path);
		
		if ($path === '')
		{
			return [];
		}

		$separator = $this->getSeparator($separator);

		if (strpos($path, $separator) === false)
		{
			return [$path];
		}

		return explode($separator, preg_replace('#[' . preg_quote($separator, '#') . ']{2,}#', $separator, $path));
	}

	/**
	 * Get a path node value.
	 *
	 * @param   string  $path       Registry Path (e.g. joomla.content.showauthor)
	 * @param   string  $separator  The key separator
	 *
	 * @return  array  The path nodes.
	 *
	 * @since   2.0.0
	 */
	private function getNodeValue($path, $separator = null)
	{
		// Get the path nodes.
		$pathNodes = $this->getPathNodes($path, $separator);

		if ($pathNodes === [])
		{
			throw new Exception('Node does not exist.');
		}

		if (count($pathNodes) === 1 && isset($this->data->$path) && $this->data->$path !== '')
		{
			return $this->data->$path;
		}

		// Initialize the current node to be the registry root.
		$node  = $this->data;
		$found = false;

		// Traverse the registry to find the correct node for the result.
		foreach ($pathNodes as $pathNode)
		{
			if (is_array($node) && isset($node[$pathNode]))
			{
				$node  = $node[$pathNode];
				$found = true;

				continue;
			}

			if (isset($node->$pathNode) === false)
			{
				return $default;
			}

			$node  = $node->$pathNode;
			$found = true;
		}

		if ($found === false)
		{
			throw new Exception('Node does not exist.');
		}

		return $node;
	}
}
