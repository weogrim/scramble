<?php

namespace Dedoc\Scramble\Support\Type;

class ArrayType extends AbstractType
{
    public function __construct(
        public Type $value = new MixedType,
        public Type $key = new IntegerType,
    ) {}

    public function nodes(): array
    {
        return ['value', 'key'];
    }

    public function isSame(Type $type)
    {
        return false;
    }

    public function toString(): string
    {
        if ($this->key instanceof IntegerType) {
            return sprintf('array<%s>', $this->value->toString());
        }

        return sprintf('array<%s, %s>', $this->key->toString(), $this->value->toString());
    }
}
