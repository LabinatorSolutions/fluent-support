<?php

namespace FluentSupport\Framework\Database\Orm\Casts;

use JsonSerializable;
USE FluentSupport\Framework\Support\Helper;
use ArrayObject as BaseArrayObject;
use FluentSupport\Framework\Contracts\Support\Arrayable;

class ArrayObject extends BaseArrayObject implements Arrayable, JsonSerializable
{
    /**
     * Get a collection containing the underlying array.
     *
     * @return \FluentSupport\Framework\Support\Collection
     */
    public function collect()
    {
        return Helper::collect($this->getArrayCopy());
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->getArrayCopy();
    }

    /**
     * Get the array that should be JSON serialized.
     *
     * @return array
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return $this->getArrayCopy();
    }
}
