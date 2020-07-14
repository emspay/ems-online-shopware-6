<?php declare(strict_types=1);

namespace Ginger\EmsPay\Light;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class LightEntityBase extends Entity
{
    /**
     * @var string
     */
    protected $ginger_order_id;

    public function getGingerOrderId(): string
    {
        return $this->ginger_order_id;
    }

    public function setGingerOrderId(string $ginger_order_id): void
    {
        $this->ginger_order_id = $ginger_order_id;
    }
}
?>
