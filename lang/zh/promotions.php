<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| 优惠券与促销
|--------------------------------------------------------------------------
|
| 两者放在同一个模块里，因为它们本质上是同一件事，只差一点：优惠券要输入，促销
| 会自己生效。
|
*/

return [

    'title' => '优惠券与促销',
    'intro' => '客户自己输入的折扣，以及会自动生效的折扣。',
    'new' => '创建优惠券／促销',
    'none_yet' => '还没有优惠券或促销。',
    'none_yet_hint' => '首次到店折扣是大多数门店最先做的一个。',
    'automatic' => '自动',
    'no_expiry_short' => '永不过期',
    'copy_of' => ':name（副本）',

    'summary' => [
        'active' => '进行中的促销',
        'scheduled' => '已排定',
        'expired' => '已过期',
        'redemptions' => '累计使用次数',
    ],

    'columns' => [
        'name' => '名称',
        'code' => '代码',
        'type' => '类型',
        'discount' => '折扣',
        'applies' => '适用范围',
        'starts' => '开始',
        'ends' => '结束',
        'used' => '已使用',
        'status' => '状态',
    ],

    'types' => [
        'coupon' => '优惠券',
        'offer' => '促销',
    ],

    'statuses' => [
        'draft' => '草稿',
        'scheduled' => '已排定',
        'active' => '进行中',
        'expired' => '已过期',
        'disabled' => '已停用',
    ],

    'applies' => [
        'booking' => '整个预约',
        'all_services' => '全部服务',
        'services' => '选定的服务',
        'categories' => '选定的分类',
    ],

    'filters' => [
        'all_statuses' => '全部状态',
        'all_types' => '全部类型',
        'all_locations' => '全部门店',
        'reset' => '重置',
    ],

    'search' => '按名称、代码或服务搜索…',
    'results' => [
        'zero' => '没有符合的优惠券或促销',
        'one' => '1 个优惠券或促销',
        'many' => ':count 个优惠券和促销',
        'clear' => '清除筛选',
    ],
    'showing' => '显示第 :from–:to 条，共 :total 条',
    'empty' => '没有符合这些筛选条件的内容。',
    'actions_for' => '对 :name 的操作',

    /* -------------------------------------------------------------- 表单 */

    'form' => [
        'create_title' => '创建优惠券或促销',
        'edit_title' => '编辑优惠券或促销',

        'templates' => '从模板开始',
        'templates_hint' => '模板只是把表单填好。它设定的一切，在你保存之前都可以改。',
        'scratch' => '从空白开始',

        'basics' => '基本信息',
        'name' => '优惠名称',
        'name_placeholder' => '新客户八折',
        'description' => '内部说明',
        'description_hint' => '写给团队看的，不是给客户看的。',
        'type' => '优惠类型',
        'type_coupon' => '优惠券代码',
        'type_coupon_hint' => '由客户或前台输入。',
        'type_offer' => '自动促销',
        'type_offer_hint' => '会自动应用到符合条件的任何预约上。',
        'code' => '优惠券代码',
        'code_hint' => '字母、数字和连字符。保存时会转为大写。',
        'generate' => '生成',

        'discount' => '折扣',
        'discount_type' => '折扣类型',
        'percent' => '百分比',
        'fixed' => '固定金额',
        'amount' => '金额',
        'percent_hint' => '按它适用的那部分金额计算的百分比，最高 100。',
        'fixed_hint' => '直接减去一个固定金额，绝不会超过它适用的那部分。',

        'applies' => '适用范围',
        'applies_hint' => '百分比是从它适用的那一部分里减，而不是整张账单。',
        'services' => '服务',
        'categories' => '分类',

        'locations' => '门店',
        'all_locations' => '全部门店',
        'selected_locations' => '选定的门店',

        'validity' => '有效期',
        'starts' => '开始日期',
        'ends' => '结束日期',
        'no_expiry' => '不设结束日期',
        'days' => '可用的星期',
        'days_hint' => '除非这个优惠只针对特定几天，否则请把每一天都勾上——周二空着的位子，没法在周三再卖一次。',

        'eligibility' => '面向谁',
        'eligibility_all' => '全部客户',
        'eligibility_new' => '仅限新客户',
        'eligibility_new_hint' => '还没有人为他们完成过一次服务。',
        'eligibility_existing' => '仅限老客户',
        'eligibility_selected' => '选定的客户',
        'clients' => '客户',

        'redemption' => '使用规则',
        'min_spend' => '最低预约金额',
        'min_spend_hint' => '选填。防止固定金额的折扣被用在金额很小的预约上。',
        'total_limit' => '总使用次数',
        'total_limit_hint' => '留空表示不限。',
        'per_client_limit' => '每位客户',
        'per_client_limit_hint' => '留空表示不限。通常的答案是每人一次。',

        'availability' => '可以在哪里使用',
        'allow_online' => '允许客户在在线预约时使用',
        'combinable' => '可以与其他优惠叠加',
        'combinable_hint' => '关闭是稳妥的答案：一个预约只用一个优惠。',
        'draft' => '存为草稿',
        'draft_hint' => '在你把这项关掉之前，草稿不会提供给任何人。',

        'save' => '保存',
        'cancel' => '取消',
    ],

    'weekdays' => [
        0 => '星期日',
        1 => '星期一',
        2 => '星期二',
        3 => '星期三',
        4 => '星期四',
        5 => '星期五',
        6 => '星期六',
    ],

    /* ------------------------------------------------------------ 详情 */

    'details' => [
        'title' => '促销详情',
        'discount' => '折扣',
        'for' => '面向',
        'services' => '服务',
        'locations' => '门店',
        'valid' => '有效期',
        'usage' => '使用情况',
        'online' => '在线预约',
        'online_yes' => '客户可以自己使用',
        'online_no' => '仅限员工',
        'created_by' => '由 :name 创建',
        'no_expiry' => '永不过期',
        'every_day' => '每天',
    ],

    'report' => [
        'title' => '效果如何',
        'redemptions' => '使用次数',
        'clients' => '客户数',
        'discount' => '已让出的折扣',
        'revenue' => '这些预约的营收',
        'revenue_hint' => '这是用过它的那些预约总共有多少钱——并不是说这个促销带来了它们。',
        'none' => '还没有人用过。',
    ],

    'actions' => [
        'view' => '查看',
        'edit' => '编辑',
        'duplicate' => '复制',
        'disable' => '停用',
        'enable' => '启用',
        'back' => '优惠券与促销',
    ],

    /* --------------------------------------------------------- 拒绝的理由 */

    'refused' => [
        'not_running' => '该优惠目前没有在进行。',
        'not_started' => '该优惠还没有开始。',
        'expired' => '该优惠已过期。',
        'wrong_day' => '该优惠在这一天不适用。',
        'wrong_location' => '该优惠在这家门店不可用。',
        'needs_a_client' => '该优惠只面向特定客户，因此这个预约需要有一位客户。',
        'new_only' => '该优惠仅限新客户。',
        'existing_only' => '该优惠仅限老客户。',
        'not_for_this_client' => '该优惠不适用于这位客户。',
        'no_eligible_services' => '这个预约里没有符合该优惠条件的内容。',
        'under_minimum' => '该优惠要求预约金额至少 :amount。',
        'fully_redeemed' => '该优惠的使用次数已经用完。',
        'client_limit' => '这位客户已经用过该优惠了。',
        'unknown_code' => '没有使用该代码的优惠。',
    ],

    'created' => '优惠券或促销已创建。',
    'saved' => '优惠券或促销已保存。',
    'duplicated' => '已复制。它被保存为草稿。',
    'disabled' => '促销已停用。',
    'enabled' => '促销已启用。',
];
