<?php

namespace Ginger\EmsPay\Light;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void              add(CustomEntity $entity)
 * @method void              set(string $key, CustomEntity $entity)
 * @method CustomEntity[]    getIterator()
 * @method CustomEntity[]    getElements()
 * @method CustomEntity|null get(string $key)
 * @method CustomEntity|null first()
 * @method CustomEntity|null last()
 */

class LightEntityCollection extends EntityCollection
{
    protected function getExpectedClass(): string
        {
            return LightEntityBase::class;
        }

}
