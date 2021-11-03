<?php
namespace GingerPlugin\Components;

use GingerPlugin\Components\GingerConfig;

class BankConfig extends GingerConfig
{
    const PAYMENT_METHODS_PREFIX = "EMS Online";
    const FILE_PREFIX = "ems_payments";
    const API_ENDPOINT = 'https://api.online.emspay.eu';
    const PLUGIN_TECH_PREFIX = 'emspay';
    const PLUGIN_NAME = 'ems-online';
}