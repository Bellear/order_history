{capture name="mainbox"}
{if $runtime.mode == "new"}
    <p>{__("text_admin_new_orders")}</p>
{/if}
{$order_status_descr = $smarty.const.STATUSES_ORDER|fn_get_simple_statuses:true:true}
{$order_statuses = $smarty.const.STATUSES_ORDER|fn_get_statuses:$statuses:true:true}

{capture name="sidebar"}
    {include file="common/saved_search.tpl" dispatch="order_history.get_order_history" view_type="orders"}
    {include file="views/orders/components/orders_search_form.tpl" dispatch="orders.manage"}
{/capture}

<form action="{""|fn_url}" method="post" target="_self" name="orders_list_form" id="orders_list_form">
    {include file="common/pagination.tpl" save_current_page=true save_current_url=true div_id=$smarty.request.content_id}
    {$c_url=$config.current_url|fn_query_remove:"sort_by":"sort_order"}
    {$c_icon="<i class=\"icon-`$search.sort_order_rev`\"></i>"}
    {$c_dummy="<i class=\"icon-dummy\"></i>"}
    {$rev=$smarty.request.content_id|default:"pagination_contents"}
    {$page_title=__("order_history")}
    {$extra_status=$config.current_url|escape:"url"}
    {$notify_vendor = fn_allowed_for("MULTIVENDOR")}
    {$notify=true}
    {$notify_department=true}

{if $orders}
<div class="table-responsive-wrapper longtap-selection">
<table width="100%" class="table table-middle table--relative table-responsive table-manage-orders">

<thead data-ca-bulkedit-default-object="true">
<tr>
    <th width="6%" class="left mobile-hide">
        {include file="common/check_items.tpl" check_statuses=$order_status_descr}
        <input type="checkbox"
               class="bulkedit-toggler hide"
               data-ca-bulkedit-toggler="true"
               data-ca-bulkedit-disable="[data-ca-bulkedit-default-object=true]"
               data-ca-bulkedit-enable="[data-ca-bulkedit-expanded-object=true]"
        />
    </th>
    <th width="5%"><a class="cm-ajax"
        href="{"`$c_url`&sort_by=order_id&sort_order=`$search.sort_order_rev`"|fn_url}"
        data-ca-target-id={$rev}>{__("id")}{if $search.sort_by == "order_id"}{$c_icon nofilter}{else}{$c_dummy nofilter}{/if}</a>
    </th>
    <th width="15%"><a class="cm-ajax"
        href="{"`$c_url`&sort_by=status&sort_order=`$search.sort_order_rev`"|fn_url}"
        data-ca-target-id={$rev}>{__("status")}{if $search.sort_by == "status"}{$c_icon nofilter}{else}{$c_dummy nofilter}{/if}</a>
    </th>
    <th width="15%"><a class="cm-ajax"
        href="{"`$c_url`&sort_by=old_status&sort_order=`$search.sort_order_rev`"|fn_url}"
        data-ca-target-id={$rev}>{__("old_status")}{if $search.sort_by == "old_status"}{$c_icon nofilter}{else}{$c_dummy nofilter}{/if}</a>
    </th>
    <th width="24%"><a class="cm-ajax"
        href="{"`$c_url`&sort_by=ch_customer&sort_order=`$search.sort_order_rev`"|fn_url}"
        data-ca-target-id={$rev}>{__("user_changed_status")}{if $search.sort_by == "ch_customer"}{$c_icon nofilter}{else}{$c_dummy nofilter}{/if}</a>
    </th>
    <th width="15%"><a class="cm-ajax"
        href="{"`$c_url`&sort_by=time_status_change&sort_order=`$search.sort_order_rev`"|fn_url}"
        data-ca-target-id={$rev}>{__("time_status_change")}{if $search.sort_by == "time_status_change"}{$c_icon nofilter}{else}{$c_dummy nofilter}{/if}</a>
    </th>
    <th class="mobile-hide">&nbsp;</th>
    <th width="10%" class="right"><a
        class="cm-ajax{if $search.sort_by == "total"} sort-link-{$search.sort_order_rev}{/if}"
        href="{"`$c_url`&sort_by=total&sort_order=`$search.sort_order_rev`"|fn_url}"
        data-ca-target-id={$rev}>{__("total")}</a></th>
</tr>
</thead>

{foreach from=$orders item="o"}
    <tr class="cm-longtap-target"
        data-ca-longtap-action="setCheckBox"
        data-ca-longtap-target="input.cm-item"
        data-ca-id="{$o.order_id}"
    >
        <td width="6%" class="left mobile-hide">
            <input type="checkbox" name="order_ids[]" value="{$o.order_id}"
                   class="cm-item cm-item-status-{$o.status|lower} hide"/>
        </td>
        <td width="15%" data-th="{__("id")}">
            <a href="{"orders.details?order_id=`$o.order_id`"|fn_url}"
               class="underlined">{__("order")}
                <bdi>#{$o.order_id}</bdi>
            </a>
            {if $order_statuses[$o.status].params.appearance_type == "I" && $o.invoice_id}
                <p class="muted">{__("invoice")} #{$o.invoice_id}</p>
            {elseif $order_statuses[$o.status].params.appearance_type == "C" && $o.credit_memo_id}
                <p class="muted">{__("credit_memo")} #{$o.credit_memo_id}</p>
            {/if}
            {include file="views/companies/components/company_name.tpl" object=$o}
        </td>
        <td width="15%" data-th="{__("status")}">
            {include file="common/select_popup.tpl"
                suffix="o"
                order_info=$o
                id=$o.order_id
                status=$o.status
                items_status=$order_status_descr
                update_controller="order_history"
                notify=$notify
                notify_department=$notify_department
                notify_vendor=$notify_vendor
                status_target_id="orders_total,`$rev`"
                extra="&return_url=`$extra_status`"
                statuses=$order_statuses
                btn_meta="btn btn-info o-status-`$o.status` order-status btn-small"|lower
                text_wrap=true
            }
            {if $o.issuer_id}
                {if $o.issuer_name|trim}
                    <p class="muted shift-left manager-order">{$o.issuer_name}</p>
                {else}
                    <p class="muted shift-left manager-order">{$o.issuer_email}</p>
                {/if}
            {/if}
        </td>
        <td width="15%" class="nowrap" data-th="{__("old_status")}">
            {include file="common/select_popup.tpl"
            hide_for_vendor=1
            id=$o.old_status
            status=$o.old_status
            items_status=$order_status_descr
            status_target_id="orders_total,`$rev`"
            }
        </td>
        <td width="17%" data-th="{__("user_changed_status")}">
            {if $o.user_changed_status}
                <a href="{"profiles.update?user_id=`$o.user_changed_status`"|fn_url}">{/if}{$o.ch_lastname} {$o.ch_firstname}{if $o.user_changed_status}</a>
            {/if}
        </td>
        <td width="15%" class="nowrap"
            {if $o.time_status_change != 0}
                data-th="{__("time_status_change")}">{$o.time_status_change|date_format:"`$settings.Appearance.date_format`, `$settings.Appearance.time_format`"}
            {/if}
        </td>
        <td class="center" data-th="{__("tools")}">
            {capture name="tools_items"}
                <li>{btn type="list" href="orders.details?order_id=`$o.order_id`" text={__("view")}}</li>
                <li>{btn type="list" href="order_management.edit?order_id=`$o.order_id`" text={__("edit")}}</li>
                <li>{btn type="list" href="order_management.edit?order_id=`$o.order_id`&copy=1" text={__("copy")}}</li>
                {$current_redirect_url=$config.current_url|escape:url}
                <li>{btn type="list" href="orders.delete?order_id=`$o.order_id`&redirect_url=`$current_redirect_url`" class="cm-confirm" text={__("delete")} method="POST"}</li>
            {/capture}
            <div class="hidden-tools">
                {dropdown content=$smarty.capture.tools_items}
            </div>
        </td>
        <td width="10%" class="right" data-th="{__("total")}">
            {include file="common/price.tpl" value=$o.total}
        </td>
    </tr>
{/foreach}
</table>
</div>
{else}
    <p class="no-items">{__("no_data")}</p>
{/if}

{if $orders}
    <div class="statistic clearfix" id="orders_total">
        <div class="table-wrapper">
        <table class="pull-right ">
        {if $total_pages > 1 && $search.page != "full_list"}
        <tr>
            <td>&nbsp;</td>
            <td width="100px">{__("for_this_page_orders")}:</td>
        </tr>
        <tr>
            <td>{__("gross_total")}:</td>
            <td>{include file="common/price.tpl" value=$display_totals.gross_total}</td>
        </tr>
        <tr>
            <td>{__("totally_paid")}:</td>
            <td>{include file="common/price.tpl" value=$display_totals.totally_paid}</td>
        </tr>
        <tr>
            <td>{__("for_all_found_orders")}:</td>
        </tr>
        {/if}
        <tr>
            <td class="shift-right">{__("gross_total")}:</td>
            <td>{include file="common/price.tpl" value=$totals.gross_total}</td>
        </tr>
        <tr>
            <td class="shift-right"><h4>{__("totally_paid")}:</h4></td>
            <td class="price">{include file="common/price.tpl" value=$totals.totally_paid}</td>
        </tr>
        </table>
        </div>
    <</div>
{/if}

{include file="common/pagination.tpl" div_id=$smarty.request.content_id}

{capture name="adv_buttons"}
    {include file="common/tools.tpl" tool_href="order_management.new" prefix="bottom" hide_tools="true" title=__("add_order") icon="icon-plus"}
{/capture}
</form>
{/capture}

{capture name="buttons"}
    {capture name="tools_list"}
    {/capture}
    {dropdown content=$smarty.capture.tools_list}
{/capture}

{include file="common/mainbox.tpl"
title=$page_title
sidebar=$smarty.capture.sidebar
content=$smarty.capture.mainbox
buttons=$smarty.capture.buttons
adv_buttons=$smarty.capture.adv_buttons
content_id="manage_orders"
select_storefront=true
storefront_switcher_param_name="storefront_id"
selected_storefront_id=$selected_storefront_id
}
