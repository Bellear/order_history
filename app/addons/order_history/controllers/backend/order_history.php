<?php

use Tygh\Notifications\EventIdProviders\OrderProvider;
use Tygh\Pdf;
use Tygh\Registry;
use Tygh\Tygh;
use Tygh\Tools\Url;

if (!defined('BOOTSTRAP')) { die('Access denied'); }

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $suffix = '';
    if ($mode == 'update_status') {
        $order_info = fn_get_order_short_info($_REQUEST['id']);
        $old_status = $order_info['status'];
        if (fn_change_order_status($_REQUEST['id'], $_REQUEST['status'], '', fn_get_notification_rules($_REQUEST))) {
            $order_info = fn_get_order_short_info($_REQUEST['id']);
            fn_check_first_order($order_info);
            $new_status = $order_info['status'];
            if ($_REQUEST['status'] != $new_status) {
                Tygh::$app['ajax']->assign('return_status', $new_status);
                Tygh::$app['ajax']->assign('color', fn_get_status_param_value($new_status, 'color'));
                fn_set_notification('W', __('warning'), __('status_changed'));
            } else {
                fn_set_notification('N', __('notice'), __('status_changed'));
            }
        } else {
            fn_set_notification('E', __('error'), __('error_status_not_changed'));
            Tygh::$app['ajax']->assign('return_status', $old_status);
            Tygh::$app['ajax']->assign('color', fn_get_status_param_value($old_status, 'color'));
        }
        if (empty($_REQUEST['return_url'])) {
            exit;
        } else {
            return array(CONTROLLER_STATUS_REDIRECT, $_REQUEST['return_url']);
        }
    }
    if ($mode === 'get_order_history') {
        $params = [];
        if (!empty($_REQUEST['user_ids'])) {
            $params['user_id'] = (array) $_REQUEST['user_ids'];
        }
        if (!empty($_REQUEST['company_ids'])) {
            $params['company_ids'] = (array) $_REQUEST['company_ids'];
        }
        if (!empty($params)) {
            unset($_REQUEST['redirect_url'], $_REQUEST['page']);
            return [CONTROLLER_STATUS_REDIRECT, Url::buildUrn(['orders', 'manage'], $params)];
        }
    }
    return [CONTROLLER_STATUS_OK, 'get_order_history' . $suffix];
}

$params = $_REQUEST;

if ($mode == 'get_order_history') {

    $params['include_incompleted'] = true;

    if (fn_allowed_for('MULTIVENDOR')) {
        $params['company_name'] = true;
    }
    if (isset($params['phone'])) {
        $params['phone'] = str_replace(' ', '', preg_replace('/[^0-9\s]/', '', $params['phone']));
    }
    list($orders, $search, $totals) = fn_get_orders($params, Registry::get('settings.Appearance.admin_elements_per_page'), true);
    //fn_print_die($search);
    if (!empty($_REQUEST['redirect_if_one']) && count($orders) == 1) {
        return array(CONTROLLER_STATUS_REDIRECT, 'orders.details?order_id=' . $orders[0]['order_id']);
    }
    $company_id = fn_get_runtime_company_id();
    $shippings = fn_get_available_shippings($company_id);
    $shippings = array_column($shippings, 'shipping', 'shipping_id');
    $remove_cc = db_get_field(
        "SELECT COUNT(*)"
        . " FROM ?:status_data"
        . " WHERE status_id IN (?n)"
        . " AND param = 'remove_cc_info'"
        . " AND value = 'N'",
        array_keys(fn_get_statuses_by_type(STATUSES_ORDER))
    );
    $remove_cc = $remove_cc > 0 ? true : false;
    Tygh::$app['view']->assign('remove_cc', $remove_cc);

    Tygh::$app['view']->assign('orders', $orders);
    Tygh::$app['view']->assign('search', $search);

    Tygh::$app['view']->assign('totals', $totals);
    Tygh::$app['view']->assign('display_totals', fn_display_order_totals($orders));
    Tygh::$app['view']->assign('shippings', $shippings);

    $payments = fn_get_payments(array('simple' => true));
    Tygh::$app['view']->assign('payments', $payments);

    if (fn_allowed_for('MULTIVENDOR')) {
        Tygh::$app['view']->assign('selected_storefront_id', empty($_REQUEST['storefront_id']) ? 0 : (int) $_REQUEST['storefront_id']);
    }
}

function fn_display_order_totals($orders)
{
    $result = array();
    $result['gross_total'] = 0;
    $result['totally_paid'] = 0;

    if (is_array($orders)) {
        foreach ($orders as $k => $v) {
            $result['gross_total'] += $v['total'];
            if ($v['status'] == 'C' || $v['status'] == 'P') {
                $result['totally_paid'] += $v['total'];
            }
        }
    }

    return $result;
}

function fn_print_order_packing_slips($order_ids, $pdf = false, $lang_code = CART_LANGUAGE)
{
    /** @var \Tygh\SmartyEngine\Core $view */
    $view = Tygh::$app['view'];
    $html = array();

    if (!is_array($order_ids)) {
        $order_ids = array($order_ids);
    }

    if ($pdf == true) {
        fn_disable_live_editor_mode();
    }

    foreach ($order_ids as $order_id) {
        if (Registry::get('settings.Appearance.email_templates') == 'old') {
            $order_info = fn_get_order_info($order_id, false, true, false, false);

            if (empty($order_info)) {
                continue;
            }

            list($shipments) = fn_get_shipments_info(array('order_id' => $order_info['order_id'], 'advanced_info' => true));

            $view->assign('order_info', $order_info);
            $view->assign('shipments', $shipments);

            $html[] = $view->displayMail('orders/print_packing_slip.tpl', false, 'A', $order_info['company_id'], $lang_code);
        } else {
            /** @var \Tygh\Template\Document\PackingSlip\Type $packing_slip */
            $packing_slip = Tygh::$app['template.document.packing_slip.type'];
            $result = $packing_slip->renderByOrderId($order_id, $lang_code);

            if (!$result) {
                continue;
            }

            $view->assign('content', $result);
            $result = $view->displayMail('common/wrap_document.tpl', false, 'A');
            $html[] = $result;
        }
        if ($pdf == false && $order_id != end($order_ids)) {
            $html[] = "<div style='page-break-before: always;'>&nbsp;</div>";
        }
    }

    if ($pdf == true) {
        return Pdf::render($html, __('packing_slip') . '-' . implode('-', $order_ids));
    }

    return implode("\n", $html);
}

function fn_update_order_details(array $params)
{
    // Update customer's email if its changed in customer's account
    if (!empty($params['update_customer_details']) && $params['update_customer_details'] == 'Y') {
        $u_id = db_get_field("SELECT user_id FROM ?:orders WHERE order_id = ?i", $params['order_id']);
        $current_email = db_get_field("SELECT email FROM ?:users WHERE user_id = ?i", $u_id);
        db_query("UPDATE ?:orders SET email = ?s WHERE order_id = ?i", $current_email, $params['order_id']);
    }

    // Log order update
    fn_log_event('orders', 'update', array(
        'order_id' => $params['order_id'],
    ));

    db_query('UPDATE ?:orders SET ?u WHERE order_id = ?i', $params['update_order'], $params['order_id']);

    $force_notification = fn_get_notification_rules($params);

    //Update shipping info
    if (!empty($params['update_shipping'])) {
        foreach ($params['update_shipping'] as $group_key => $shipment_group) {
            foreach($shipment_group as $shipment_id => $shipment) {
                $shipment['order_id'] = $params['order_id'];
                fn_update_shipment($shipment, $shipment_id, $group_key, true, $force_notification);
            }
        }
    }

    $edp_data = array();
    $order_info = fn_get_order_info($params['order_id']);
    if (!empty($params['activate_files'])) {
        $edp_data = fn_generate_ekeys_for_edp(array(), $order_info, $params['activate_files']);
    }

    /** @var \Tygh\Notifications\EventDispatcher $event_dispatcher */
    $event_dispatcher = Tygh::$app['event.dispatcher'];
    /** @var \Tygh\Notifications\Settings\Factory $notification_settings_factory */
    $notification_settings_factory = Tygh::$app['event.notification_settings.factory'];
    $notification_rules = $notification_settings_factory->create($force_notification);

    $event_dispatcher->dispatch(
        'order.updated',
        ['order_info' => $order_info],
        $notification_rules,
        new OrderProvider($order_info)
    );
    if ($edp_data) {
        $notification_rules = fn_get_edp_notification_rules($force_notification, $edp_data);
        $event_dispatcher->dispatch(
            'order.edp',
            [
                'order_info' => $order_info,
                'edp_data' => $edp_data
            ],
            $notification_rules,
            new OrderProvider($order_info, $edp_data)
        );
    }

    fn_order_notification($order_info, $edp_data, $force_notification);

    if (!empty($params['prolongate_data']) && is_array($params['prolongate_data'])) {
        foreach ($params['prolongate_data'] as $ekey => $v) {
            $newttl = fn_parse_date($v, true);
            db_query('UPDATE ?:product_file_ekeys SET ?u WHERE ekey = ?s', array('ttl' => $newttl), $ekey);
        }
    }

    // Update file downloads section
    if (!empty($params['edp_downloads'])) {
        foreach ($params['edp_downloads'] as $ekey => $v) {
            foreach ($v as $file_id => $downloads) {
                $max_downloads = db_get_field("SELECT max_downloads FROM ?:product_files WHERE file_id = ?i", $file_id);
                if (!empty($max_downloads)) {
                    db_query('UPDATE ?:product_file_ekeys SET ?u WHERE ekey = ?s', array('downloads' => $max_downloads - $downloads), $ekey);
                }
            }
        }
    }

    /**
     * Executes after order details were updated in the administration panel, allows to perform additional actions
     * like sending notifications.
     *
     * @param array $params             Order details
     * @param array $order_info         Order information
     * @param array $edp_data           Downloadable products data
     * @param array $force_notification Notification rules
     */
    fn_set_hook('update_order_details_post', $params, $order_info, $edp_data, $force_notification);
}
