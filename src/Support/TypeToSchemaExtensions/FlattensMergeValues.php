<?php

namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

use Carbon\Carbon;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralBooleanType;
use Dedoc\Scramble\Support\Type\StringType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeWalker;
use Dedoc\Scramble\Support\Type\Union;
use Dedoc\Scramble\Support\Type\UnknownType;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MergeValue;
use Illuminate\Http\Resources\MissingValue;

trait FlattensMergeValues
{
    protected function flattenMergeValues(array $items)
    {
        return collect($items)
            ->flatMap(function (ArrayItemType_ $item) {
                if ($item->value instanceof KeyedArrayType) {
                    $item->value->items = $this->flattenMergeValues($item->value->items);
                    $item->value->isList = KeyedArrayType::checkIsList($item->value->items);

                    return [$item];
                }

                if (
                    $item->value instanceof Union
                    && (new TypeWalker)->first($item->value, fn (Type $t) => $t->isInstanceOf(Carbon::class))
                ) {
                    (new TypeWalker)->replace($item->value, function (Type $t) {
                        return $t->isInstanceOf(Carbon::class)
                            ? tap(new StringType, fn ($t) => $t->setAttribute('format', 'date-time'))
                            : null;
                    });

                    return [$item];
                }

                if ($item->value->isInstanceOf(JsonResource::class)) {
                    $resource = $this->getResourceType($item->value);

                    if ($resource->isInstanceOf(MissingValue::class)) {
                        return [];
                    }

                    if (
                        $resource instanceof Union
                        && (new TypeWalker)->first($resource, fn (Type $t) => $t->isInstanceOf(MissingValue::class))
                    ) {
                        $item->isOptional = true;

                        return [$item];
                    }
                }

                if (
                    $item->value instanceof Union
                    && (new TypeWalker)->first($item->value, fn (Type $t) => $t->isInstanceOf(MissingValue::class))
                ) {
                    $newType = array_filter($item->value->types, fn (Type $t) => ! $t->isInstanceOf(MissingValue::class));

                    if (! count($newType)) {
                        return [];
                    }

                    $item->isOptional = true;

                    if (count($newType) === 1) {
                        $item->value = $newType[0];

                        return $this->flattenMergeValues([$item]);
                    }

                    $item->value = new Union($newType);

                    return $this->flattenMergeValues([$item]);
                }

                if (
                    $item->value instanceof Generic
                    && $item->value->isInstanceOf(MergeValue::class)
                ) {
                    $arrayToMerge = $item->value->templateTypes[1];

                    // Second generic argument of the `MergeValue` class must be a keyed array.
                    // Otherwise, we ignore it from the resulting array.
                    if (! $arrayToMerge instanceof KeyedArrayType) {
                        return [];
                    }

                    $arrayToMergeItems = $this->flattenMergeValues($arrayToMerge->items);

                    $mergingArrayValuesShouldBeRequired = $item->value->templateTypes[0] instanceof LiteralBooleanType
                        && $item->value->templateTypes[0]->value === true;

                    if (! $mergingArrayValuesShouldBeRequired || $item->isOptional) {
                        foreach ($arrayToMergeItems as $mergingItem) {
                            $mergingItem->isOptional = true;
                        }
                    }

                    return $arrayToMergeItems;
                }

                return [$item];
            })
            ->values()
            ->all();
    }

    /**
     * @todo Maybe does not belong here as simply provides a knowledge about locating a type in a json resource generics.
     * This is something similar to Scramble's PRO wrap handling logic.
     */
    private function getResourceType(Type $type): Type
    {
        if (! $type instanceof Generic) {
            return new UnknownType();
        }

        if ($type->isInstanceOf(AnonymousResourceCollection::class)) {
            return $type->templateTypes[0]->templateTypes[0]
                ?? new UnknownType();
        }

        if ($type->isInstanceOf(JsonResource::class)) {
            return $type->templateTypes[0];
        }

        return new UnknownType();
    }
}
