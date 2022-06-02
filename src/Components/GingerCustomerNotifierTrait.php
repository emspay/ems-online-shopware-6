<?php

namespace GingerPlugin\Components;

use Symfony\Component\HttpFoundation\Session\Session;

trait GingerCustomerNotifierTrait
{
    public function showWarning($event, $message)
    {
        $type = 'warning';


            $session = $event->getRequest()->getSession();



        $flash_bag = $session->getFlashBag();
        $flash_bag->add($type, $message);
    }
}