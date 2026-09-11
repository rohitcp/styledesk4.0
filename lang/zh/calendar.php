<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Calendar
|--------------------------------------------------------------------------
|
| The diary drawn against the clock: who is working, who is booked, and where
| the gaps are.
|
*/

return [
    'title' => '日历',
    'subtitle' => '谁在上班、谁有预约、哪里还有空档。',

    'nav' => [
        'previous' => '前一天',
        'next' => '后一天',
        'today' => '今天',
        'pick' => '选择日期',
    ],

    'views' => [
        'day' => '日',
        'week' => '周',
        'month' => '月',
    ],

    'filters' => [
        'location' => '门店',
        'staff' => '员工',
        'resource' => '资源',
        'all_staff' => '全部员工',
        'all_resources' => '全部资源',
        'all_locations' => '全部门店',
        'interval' => '时间间隔',
        'minutes' => ':count 分钟',
        'service' => '服务',
        'all_services' => '全部服务',
        'search' => '搜索…',
    ],

    'summary' => [
        'total' => '预约',
        'arrived' => '已签到',
        'completed' => '已完成',
        'cancelled' => '已取消',
        'no_show' => '未到店',
        'owing' => '待付余额',
        'revenue' => '预计营收',
    ],

    'card' => [
        'balance' => '待付余额 · :amount',
        'membership' => '会员',
        /* Reserved until the client walks in — the rule the redemption engine
           keeps. A calendar that called a future appointment "used" would be
           telling the client they had already had it. */
        'credits_reserved' => '会员 · 已预留 :count 个额度|会员 · 已预留 :count 个额度',
        'credits_used' => '会员 · 已使用 :count 个额度|会员 · 已使用 :count 个额度',
        'note' => '含备注',
    ],

    'more' => '另有 :count 个',
    'preview' => [
        'status' => '状态',
        'payment' => '付款',
    ],

    'now' => '现在 · :time',
    'break' => ':minutes 分钟休息',
    'off' => '不上班',
    'closed' => '当天休息。',
    'no_staff' => '该门店尚未设置可提供服务的员工。',
    'empty' => '当天没有预约。',
    'loading' => '正在加载当天…',
    'new_booking' => '+ 新建预约',
    'free_slot' => '为 :staff 预约 :time',
];
