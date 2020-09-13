<?php

if (!defined('BOOTSTRAP')) { die('Access denied'); }

fn_register_hooks(
    'change_order_status_post',
    'pre_get_orders'
);
