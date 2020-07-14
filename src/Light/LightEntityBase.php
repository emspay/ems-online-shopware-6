<?php declare(strict_types=1);

namespace Ginger\EmsPay\Light;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class LightEntityBase extends Entity
{
    /**
     * @var string
     */
    protected $ems_order_id;

    public function getGingerOrderId(): string
    {
        return $this->ems_order_id;
    }

    public function setGingerOrderId(string $ems_order_id): void
    {
        $this->ems_order_id = $ems_order_id;
    }
}
?>
