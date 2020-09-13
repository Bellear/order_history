<?php

if (!defined('BOOTSTRAP')) { die('Access denied'); }

function fn_order_history_change_order_status_post(&$order_id, $status_to, $status_from, $force_notification, $place_order, $order_info, $edp_data) {
    db_query("UPDATE ?:orders SET old_status = ?s WHERE order_id = ?i", $status_from, $order_id);
    $user_id = $_SESSION[auth][user_id];
    $ch_firstname = db_get_field("SELECT firstname FROM ?:users WHERE user_id = ?i", $user_id);
    $ch_lastname = db_get_field("SELECT lastname FROM ?:users WHERE user_id = ?i", $user_id);
    db_query("UPDATE ?:orders SET ch_firstname = ?s WHERE order_id = ?i", $ch_firstname, $order_id);
    db_query("UPDATE ?:orders SET ch_lastname = ?s WHERE order_id = ?i", $ch_lastname, $order_id);
    db_query("UPDATE ?:orders SET user_changed_status = ?i WHERE order_id = ?i", $user_id, $order_id);
    $curr_time = time();
    db_query("UPDATE ?:orders SET time_status_change = ?i WHERE order_id = ?i", $curr_time, $order_id);
}

function fn_order_history_pre_get_orders($params, &$fields, &$sortings, $get_totals, $lang_code) {
    array_push($fields, '?:orders.old_status', '?:orders.time_status_change', '?:orders.ch_firstname', '?:orders.ch_lastname','?:orders.user_changed_status');
    $more_sortings = ['old_status' => '?:orders.old_status',
                      'time_status_change' => '?:orders.time_status_change',
                      'ch_customer' => ['?:orders.ch_lastname', '?:orders.ch_firstname']];
    $sortings += $more_sortings;

}
