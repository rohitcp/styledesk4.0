<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| 门店动态
|--------------------------------------------------------------------------
|
| 应用栏"动态"图标背后的面板：谁在什么时候做了什么，以及一条通往对应记录的路。
|
| 这里的措辞重点在于那些单词式的类型。"预约""取消""收款"——扫过这一列的人应该
| 不用读句子就能找到自己要找的那一类，而下面那句话因此可以专心讲别的。
|
*/

return [

    'title' => '门店动态',
    'intro' => '整个门店最近发生了什么。',
    'open' => '动态',
    'close' => '关闭动态',
    'mark_read' => '全部标为已读',
    'unread' => ':count 条新的',
    'unread_capped' => '99+ 条新的',
    'loading' => '正在加载…',
    'more' => '显示更多',
    'empty' => '还没有发生任何事。',
    'empty_hint' => '门店里的预约、收款和各种变动，会在发生时出现在这里。',
    'empty_filtered' => '还没有这一类的内容。',

    'today' => '今天',
    'yesterday' => '昨天',
    'earlier' => '更早',

    'by' => '由 :name',
    'system' => 'StyleDesk',

    /*
    | 每个都是一个词，绝不用两个。类型是读者一扫而过的标签，不是要读的句子。
    */
    'kinds' => [
        'booking' => '预约',
        'reschedule' => '改期',
        'cancel' => '取消',
        'checkin' => '签到',
        'checkout' => '结账',
        'noshow' => '爽约',
        'client' => '客户',
        'note' => '备注',
        'file' => '文件',
        'payment' => '收款',
        'deposit' => '定金',
        'refund' => '退款',
        'staff' => '员工',
        'schedule' => '排班',
        'service' => '服务',
        'resource' => '资源',
        'email' => '邮件',
        'sms' => '短信',
        'review' => '评价',
        'coupon' => '优惠券',
        'giftcard' => '礼品卡',
        'login' => '登录',
        'settings' => '设置',
    ],

    /* 顶部的一排筛选标签。 */
    'groups' => [
        'all' => '全部',
        'bookings' => '预约',
        'clients' => '客户',
        'payments' => '收款',
        'staff' => '员工',
        'scheduling' => '排班',
        'communication' => '沟通',
        'system' => '系统',
    ],

    /* 点击这一行会去往哪里。 */
    'links' => [
        'booking' => '查看预约',
        'client' => '查看客户',
        'staff' => '查看员工',
        'schedule' => '查看排班',
        'service' => '查看服务',
        'resource' => '查看资源',
        'payment' => '查看收款',
    ],

    /*
    | 这个页面自己写出来的句子，用于那些只存事实、不存文字的来源。凡是来自客户时间线
    | 的内容，都是已经写好的，按存下来的样子原样显示。
    */
    'sentences' => [
        'status' => '预约 :reference 被标记为 :status。',
        'reason' => '原因：:reason。',
        'schedule' => '已为 :name 发布排班——共 :count 个班次。',
    ],

    /*
    | 管理性变更，按审计日志里记录的动作索引。没有对应条目的，会退回到动作本身的
    | 说法，而不是什么都不显示。
    */
    'audit' => [
        'fallback' => ':action —— :name',
        'staff.created' => ':name 已加入团队。',
        'staff.edited' => ':name 的资料已更新。',
        'staff.role_changed' => ':name 的角色已更改。',
        'staff.deleted' => ':name 已从团队中移除。',
        'staff.invitation_sent' => ':name 已收到加入邀请。',
        'auth.login' => ':name 登录了。',
        'auth.logout' => ':name 退出了登录。',
        'auth.password_reset' => ':name 的密码已重置。',
    ],

];
