<?php

declare(strict_types=1);

return [

    'title' => '销售',
    'intro' => '查看收入、收款、未结余额、退款、小费和交易动态。',

    'period' => '日期范围',
    'periods' => [
        'today' => '今天',
        'tomorrow' => '明天',
        'yesterday' => '昨天',
        'last_3' => '最近 3 天',
        'last_7' => '最近 7 天',
        'week' => '本周',
        'month' => '本月',
        'custom' => '自定义范围',
    ],
    'from' => '从',
    'to' => '至',
    'apply' => '应用',

    'widgets' => [
        'total_sales' => '销售总额',
        'collected' => '已收款项',
        'outstanding' => '未结余额',
        'refunds' => '退款',
        'tips' => '已收小费',
        'transactions' => '交易笔数',
    ],

    'notes' => [
        'total_sales' => '按预约日期计',
        'collected' => '按收款日期计',
        'outstanding' => '仍然欠款',
        'refunds' => '按收款日期计',
        'tips' => '按收款日期计',
        'transactions' => '已完成的收款',
    ],

    'vs_previous' => '对比上一周期',
    'show_these' => '查看这些 →',

    /*
    | 两半的统计口径不同，页面把这点说出来。八月收下、用于九月预约的定金，是九月的
    | 销售、八月的收款；不知道这一点的人会发现总额对不上，并断定数字有问题。
    */
    'counting_note' => '销售额和未结余额按预约日期统计。收款、退款和小费按资金实际发生的时间统计。',

    'transactions' => '交易',
    'search_placeholder' => '交易号、预约、客户、邮箱或电话',
    'actions_for' => '对 :name 的操作',
    'showing' => '显示',
    'results' => [
        'zero' => '没有交易',
        'one' => '1 笔交易',
        'many' => ':count 笔交易',
        'clear' => '清除筛选',
    ],
    'empty' => '这段时间没有任何资金往来。',
    'walk_in' => '到店客人',

    'columns' => [
        'reference' => '交易',
        'at' => '日期与时间',
        'booking' => '预约',
        'client' => '客户',
        'services' => '服务',
        'staff' => '员工',
        'location' => '门店',
        'total' => '预约总额',
        /* 这一笔收款，相对于这个预约累计收到的金额——两个不同的问题，表格都回答。 */
        'amount' => '本次收款',
        'balance' => '余额',
        'method' => '方式',
        'status' => '状态',
    ],

    'drawer' => [
        'at_a_glance' => '概览',
        'nothing_owed' => '没有欠款',
        'next_appointment' => '下次到店',
        'total_visits' => '累计到访',
        'lifetime_spend' => '累计消费',
        'last_visit' => '最近到访',
        'tags' => '标签',
        'account' => '账户',
        'bookings' => '预约',
        'spend' => '累计已付',
        'owed' => '仍然欠款',
        'transaction' => '交易',
        'booking' => '预约',
        'amount' => '本次收款',
        'tip' => '小费',
        'paid_total' => '此预约已付',
        'recorded_by' => '收款人',
        'online' => '线上',
        'processor_reference' => '服务商流水号',
        'view_client' => '查看完整信息',
        'view_receipt' => '查看完整收据',
        'download' => '下载 PDF',
    ],

    'actions' => [
        'view_booking' => '查看预约',
        'open_booking' => '打开预约页面',
        'view_client' => '查看客户',
        'view_receipt' => '查看收据',
        'view_membership' => '查看会员',
        'open_membership' => '打开会员页面',
    ],
    /* What kind of thing was sold. Both are transactions; the
       filter is for a reader reconciling one at a time. */
    'type' => '类型',
    /* Plural in the filter, singular in the cell: one row is one
           transaction, and "Memberships" printed against it reads as a
           count. */
    'row_types' => [
        'service' => '服务',
        'membership' => '会员',
    ],
    'types' => [
        'all' => '所有类型',
        'service' => '服务',
        'membership' => '会员',
    ],

];
