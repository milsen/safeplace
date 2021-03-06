<?php

// define the file names of the default and modified databases
define('DEFAULT_KB','./default-kb.xml');
define('MODIFIED_KB','./user_content/modified-kb.xml');

assert_options(ASSERT_BAIL, true);

function array_filter_type($type, $array)
{
	$hits = array();

	foreach ($array as $element)
		if ($element instanceof $type)
			$hits[] = $element;

	return $hits;
}

function array_flatten($array)
{
	$values = array();

	foreach ($array as $item)
		if (is_array($item))
			$values = array_merge($values, array_flatten($item));
		else
			$values[] = $item;

	return $values;
}

function array_map_method($method, $array)
{
	$values = array();

	foreach ($array as $key => $value)
		$values[$key] = call_user_func(array($value, $method));

	return $values;
}

function iterator_contains(Iterator $it, $needle)
{
	foreach ($it as $el)
		if ($el == $needle)
			return true;

	return false;
}

function iterator_first(Iterator $it)
{
	$it->rewind();

	if (!$it->valid())
		throw new RuntimeException("Iterator has no valid elements");

	return $it->current();
}

function iterator_map(Iterator $it, Callable $callback)
{
	return new CallbackMapIterator($it, $callback);
}

class CallbackMapIterator extends IteratorIterator
{
	protected $callback;

	public function __construct(Traversable $iterator, Callable $callback)
	{
		parent::__construct($iterator);

		$this->callback = $callback;
	}

	public function current()
	{
		return call_user_func($this->callback, parent::current(), parent::key());
	}
}

class Map implements ArrayAccess, IteratorAggregate
{
	private $default_value;

	private $data = array();

	public function __construct($default_value = null)
	{
		$this->default_value = $default_value;
	}

	public function offsetExists($key)
	{
		if (!is_scalar($key))
			throw new InvalidArgumentException('$key can only be of a scalar type');

		return isset($this->data[$key]);
	}

	public function offsetUnset($key)
	{
		if (!is_scalar($key))
			throw new InvalidArgumentException('$key can only be of a scalar type');

		unset($this->data[$key]);
	}

	public function offsetGet($key)
	{
		if (!is_scalar($key))
			throw new InvalidArgumentException('$key can only be of a scalar type');

		return isset($this->data[$key])
			? $this->data[$key]
			: $this->offsetSet($key, $this->makeDefaultValue($key));
	}

	public function offsetSet($key, $value)
	{
		if (!is_scalar($key))
			throw new InvalidArgumentException('$key can only be of a scalar type');

		return $this->data[$key] = $value;
	}

	public function getIterator()
	{
		return new ArrayIterator($this->data);
	}

	public function data()
	{
		return $this->data;
	}

	protected function makeDefaultValue($key)
	{
		return is_callable($this->default_value)
			? call_user_func($this->default_value, $key)
			: $this->default_value;
	}
}

class Set implements IteratorAggregate, Countable
{
	private $values;

	public function __construct()
	{
		$this->values = array();
	}

	public function contains($value)
	{
		return in_array($value, $this->values);
	}

	public function push($value)
	{
		if (!$this->contains($value))
			$this->values[] = $value;
	}

	public function pushAll($values)
	{
		foreach ($values as $value)
			$this->push($value);
	}

	public function remove($value)
	{
		$index = array_search($value, $this->values);

		return $index !== false
			? array_splice($this->values, $index, 1)
			: false;
	}

	public function getIterator()
	{
		return new ArrayIterator($this->values);
	}

	public function count()
	{
		return count($this->values);
	}

	public function isEmpty()
	{
		return $this->count() === 0;
	}

	public function elem($index)
	{
		return $this->values[$index];
	}

	/**
	 * Sort the set using a given comparison-function.
	 *
	 * @return void
	 */
	public function sort(callable $func)
	{
		usort($this->values, $func);
	}
}

/**
 * In oudere versies van PHP is het serializen van SplStack niet goed
 * geïmplementeerd. Dus dan maar zelf implementeren :)
 */
class Stack extends SplStack implements Serializable
{
	public function serialize()
	{
		$items = iterator_to_array($this);
		return serialize($items);
	}

	public function unserialize($data)
	{
		foreach (unserialize($data) as $item)
			$this->unshift($item);
	}

	public function reverse()
	{
		$this->rewind();
		$s = array();

		while (!$this->isEmpty()) {
			$s[] = $this->pop();
		}

		foreach ($s as $item) {
			$this->push($item);
		}
	}
}

class Template
{
	private $__TEMPLATE__;

	private $__DATA__;

	public function __construct($file)
	{
		$this->__TEMPLATE__ = $file;

		$this->__DATA__ = array();
	}

	public function __set($key, $value)
	{
		$this->__DATA__[$key] = $value;
	}

	public function render()
	{
		ob_start();
		extract($this->__DATA__);
		include $this->__TEMPLATE__;
		return ob_get_clean();
	}

	static public function html($data)
	{
		return htmlspecialchars($data, ENT_COMPAT, 'utf-8');
	}

	static public function attr($data)
	{
		return htmlspecialchars($data, ENT_QUOTES, 'utf-8');
	}

	static public function id($data)
	{
		return preg_replace('/[^a-z0-9_]/i', '_', $data);
	}

	static public function format_plain_text($text)
	{
		$plain_paragraphs = new ArrayIterator(preg_split("/\r?\n\r?\n/", $text));

		$formatted_paragraphs = iterator_map($plain_paragraphs,
			function($plain_paragraph) {
				return sprintf('<p>%s</p>', nl2br(self::html(trim($plain_paragraph))));
			});

		return implode("\n", iterator_to_array($formatted_paragraphs));
	}

	static public function format_code($code, $line_no_offset = null)
	{
		static $line_no = 1;

		if ($line_no_offset !== null)
			$line_no = $line_no_offset;

		$wrapped_lines = array();

		foreach (explode("\n", $code) as $line)
			$wrapped_lines[] = sprintf('<pre data-lineno="%d">%s</pre>',
				$line_no++, self::html($line));

		return implode("\n", $wrapped_lines);
	}
}

// return the file name of the currently used database
function current_kb()
{
	if (file_exists(MODIFIED_KB)) {
		return MODIFIED_KB;
	} else if (file_exists(DEFAULT_KB)) {
		return DEFAULT_KB;
	} else {
		return null;
	}
}
