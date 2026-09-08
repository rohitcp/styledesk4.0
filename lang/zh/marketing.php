<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| 营销
|--------------------------------------------------------------------------
|
| 邮件营销活动：门店写给整个客户名单的内容，区别于邮件模块——那是与某一位客户的
| 一对一往来。
|
*/

return [

    'title' => '邮件营销',
    'subtitle' => '发给你的客户名单的营销活动',
    'intro' => '把客户当作一个群体来写信——优惠、动态和提醒——并看看他们做了什么。',

    'create' => '创建邮件营销活动',
    'edit' => '编辑活动',
    'saved' => '活动已保存',
    'deleted' => '活动已删除',
    'delete' => '删除草稿',
    'delete_confirm' => '删除这份草稿？它还没有发给任何人，且此操作无法撤销。',
    'save_draft' => '保存草稿',
    'cancel' => '取消',
    'search' => '搜索活动…',
    'all_statuses' => '全部状态',
    'filters_active' => '筛选',
    'clear' => '清除',

    'summary' => [
        'campaigns' => '活动数',
        'sent' => '已发送邮件',
        'delivery_rate' => '送达率',
        'open_rate' => '打开率',
        'click_rate' => '点击率',
        'unsubscribed' => '退订',
    ],

    'statuses' => [
        'draft' => '草稿',
        'scheduled' => '已排定',
        'sending' => '发送中',
        'sent' => '已发送',
        'paused' => '已暂停',
        'cancelled' => '已取消',
        'failed' => '发送失败',
    ],

    'table' => [
        'name' => '活动',
        'audience' => '受众',
        'recipients' => '收件人',
        'scheduled' => '排定时间',
        'sent' => '已发送',
        'delivered' => '已送达',
        'opened' => '已打开',
        'clicked' => '已点击',
        'author' => '创建人',
        'status' => '状态',
    ],

    /* 第一步：这封邮件是什么，以及它以谁的名义发出。 */
    'details' => [
        'title' => '活动信息',
        'intro' => '这个活动叫什么，以及你的客户在收件箱里会看到什么。',
        'name' => '活动名称',
        'name_hint' => '供你自己参考。你的客户永远看不到它。',
        'name_placeholder' => '九月按摩促销',
        'subject' => '邮件主题',
        'subject_placeholder' => '下次按摩立省 20%',
        'preview_text' => '预览文字',
        'preview_hint' => '在大多数收件箱里显示在主题后面的那一行。',
        'preview_placeholder' => '今天就预约你的九月行程。',
        'from_name' => '发件人名称',
        'reply_to' => '回复邮箱',
        'reply_hint' => '回复会发到这里。留空则使用你的门店邮箱。',
    ],

    /* 第二步：发给谁。 */
    'audience' => [
        'title' => '受众',
        'intro' => '这个活动发给谁。规则在发送时才执行，所以在那之前变大的名单也会跟着变大。',
        'scope' => '客户',
        'locations' => '门店',
        'tags' => '标签',
        'staff' => '由哪位员工服务过',
        'services' => '做过哪项服务',
        'lapsed' => '已经多久没来',
        'visited' => '在多久内来过',
        'booking' => '即将到来的预约',
        'days' => ':days 天',
        'any' => '任意',
        'has_upcoming' => '有已约的行程',
        'no_upcoming' => '没有已约的行程',

        'scopes' => [
            'all' => '全部客户',
            'active' => '正常状态的客户',
        ],

        /*
        | 列表页上的受众一列，用句子表达。
        |
        | 单独成组，否则其中四条会和上面的表单标签撞车——"已经多久没来"是一个字段
        | 标签，而"90 天内没有到访"是关于一个已保存活动的句子；共用一个键时，后者
        | 曾悄悄把前者顶掉。
        */
        'said' => [
            'locations' => '[1,*] :count 家门店',
            'tags' => '[1,*] :count 个标签',
            'staff' => '[1,*] :count 名员工',
            'services' => '[1,*] :count 项服务',
            'lapsed' => ':days 天内没有到访',
            'visited' => ':days 天内到访过',
            'has_upcoming' => '有即将到来的预约',
            'no_upcoming' => '没有已约的行程',
        ],
    ],

    /*
    | 预估。给三个数字而不是一个，因为"1,248 位客户"会掩盖店主在按下发送之前需要
    | 知道的那两个事实。
    */
    'estimate' => [
        'title' => '预计收件人',
        'eligible' => '符合条件',
        'unsubscribed' => '已退订',
        'invalid' => '邮箱无效',
        'total' => '符合你的规则',
        'counting' => '正在统计…',
        'hint' => '这是现在的统计。规则在活动发送时会再执行一次，所以这个数字可能变动。',
        'none' => '目前还没有人符合这些规则。',
        'all_unsubscribed' => '符合这些规则的人都已退订营销邮件。',
    ],

    /* 已经做好的，和还没做的。 */
    'next' => [
        'title' => '还在路上',
        'intro' => '这个活动现在可以描述并保存。设计和发送是接下来的步骤。',
        'design' => '设计邮件',
        'preview' => '预览与测试',
        'send' => '发送或排期',
        'soon' => '即将推出',
    ],

    'empty' => '还没有活动。',
    'empty_hint' => '创建一个，把客户当作一个群体来写信。',
    'no_matches' => '没有符合该搜索的活动。',

    'results' => [
        'zero' => '未找到活动',
        'one' => '找到 1 个活动',
        'many' => '找到 :count 个活动',
    ],
    'showing' => '显示第 :from–:to 个活动，共 :total 个',
    'actions_for' => '对 :name 的操作',

];
