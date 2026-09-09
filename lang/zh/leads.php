<?php

declare(strict_types=1);

/*
| 开了头却没有完成的预约。
|
| 措辞把意向当作一通值得回拨的电话，而不是一次失败。它们多半是有人挂断去确认
| 日期，如果页面称之为"已放弃"，前台就得为自己的日程表道歉。
*/

return [

    'title' => '预约意向',
    'intro' => '开了头却始终没有完成的预约。每一条都是一通值得回拨的电话。',

    'search' => '按姓名、编号或号码搜索…',
    'search_label' => '搜索意向',
    'all_statuses' => '全部状态',

    'actions' => [
        'complete' => '继续完成预约',
        'view_booking' => '查看预约',
        'view_client' => '查看客户',
    ],

    'columns' => [
        'who' => '客户',
        'services' => '想要的服务',
        'expected' => '希望的时间',
        'location' => '门店',
        'total' => '金额',
        'started' => '开始于',
        'step' => '停在',
        'status' => '状态',
    ],

    /*
    | 这条意向后来怎么样了。不是它走到了哪一步——那是下面的"步骤"，两者刻意分开：
    | "需要跟进"是说要给对方打电话，"定金与付款"是说打电话要谈什么。
    */
    'statuses' => [
        'draft' => ['label' => '草稿'],
        'new' => ['label' => '新意向'],
        'in-progress' => ['label' => '进行中'],
        'awaiting-confirmation' => ['label' => '等待客户确认'],
        'awaiting-deposit' => ['label' => '等待定金'],
        'payment-pending' => ['label' => '待付款'],
        'follow-up' => ['label' => '需要跟进'],
        'contacted' => ['label' => '已联系'],
        'converted' => ['label' => '已转化'],
        'abandoned' => ['label' => '已放弃'],
        'cancelled' => ['label' => '已取消'],
        'lost' => ['label' => '已流失'],
        'expired' => ['label' => '已过期'],
    ],

    /* 客户在预约页面走到了哪一步。 */
    'steps' => [
        'service' => '服务',
        'when' => '找谁、什么时候',
        'details' => '预约详情',
        'payment' => '定金与付款',
        'comms' => '沟通',
        'completed' => '已完成',
    ],

    /* 为什么停在了那里。 */
    'reasons' => [
        'changed-mind' => '客户改变了主意',
        'no-suitable-time' => '没有合适的时段',
        'staff-unavailable' => '偏好的员工没空',
        'price' => '价格',
        'duplicate' => '重复的请求',
        'declined' => '客户拒绝了',
        'unreachable' => '联系不上',
        'booked-elsewhere' => '在别处预约了',
        'no-availability' => '没有合适的空档',
        'other' => '其他',
    ],

    'contact_methods' => [
        'phone' => '电话',
        'sms' => '短信',
        'email' => '邮件',
        'whatsapp' => 'WhatsApp',
        'in-person' => '当面',
    ],

    /*
    | 列表在自身之上打开的抽屉。
    |
    | 还没有人填过的字段会显示"未选择"，而不是留空：空白一行读起来像坏了，而缺的
    | 那一项通常正是这通电话要谈的。
    */
    'drawer' => [
        'not_selected' => '未选择',
        'summary' => '意向',
        'created' => '创建于',
        'created_by' => '创建人',
        'last_activity' => '最近动态',
        'taken_by' => '最近联系人',
        'client' => '客户',
        'name' => '姓名',
        'phone' => '电话',
        'email' => '邮箱',
        'preferences' => '预约偏好',
        'booking' => '预约详情',
        'services' => '服务',
        'duration' => '时长',
        'price' => '价格',
        'date' => '日期',
        'time' => '时间',
        'staff' => '服务人员',
        'location' => '门店',
        'notes' => '备注',
        'payment' => '付款',
        'estimated_total' => '预计总额',
        'deposit_required' => '需付定金',
        'deposit_paid' => '已付定金',
        'outstanding' => '尚欠',
        'payment_status' => '付款状态',
        'journey' => '预约进度',
        'activity' => '动态',
        'no_activity' => '暂无记录。',
        'stopped_at' => '停在',
        'view_client' => '打开客户记录',
        'close' => '关闭',
        'send_email' => '发送邮件',
        'send_sms' => '发送短信',
        'soon' => '即将推出',
    ],

    'events' => [
        'created' => '已创建预约意向',
        'step' => '已完成：:step',
        'cancelled' => '已取消——:reason',
        'converted' => '已转化为预约',
        'contacted' => '已联系客户',
    ],

    'notes' => [
        'button' => '备注',
        'title' => '备注',
        'write' => '添加一条备注',
        'placeholder' => '下午 4 点打过，无人接听——明天再试。',
        'hint' => '会保存到客户记录并标记到这条意向上，这样下一个人从哪边都能看到。',
        'save' => '保存备注',
        'saved' => '备注已保存到客户记录。',
        'empty' => '关于这条意向还没有备注。',
        'needs_client' => '到店客人没有可以记备注的客户记录。',
    ],

    'cancel' => [
        'title' => '取消这条预约意向？',
        'body' => '这条意向会被标记为已取消并保留在历史记录中，注明由谁在何时取消。',
        'reason' => '原因',
        'choose_reason' => '选择一个原因',
        'note' => '备注',
        'note_placeholder' => '下一个人应该知道的任何事——选填。',
        'keep' => '保留意向',
        'confirm' => '取消预约意向',
        'cancelled' => '预约意向已取消。',
        'failed' => '没有保存成功，请重试。',
    ],

    'empty' => '没有符合这些筛选条件的意向。',
    'none_yet' => '还没有预约意向',
    'none_yet_hint' => '当有人开始预约却没有完成时，就会写下一条意向，让你手里有编号和服务内容可以回拨。',

    'showing' => '显示第 :from–:to 条意向，共 :total 条',
    'results' => [
        'zero' => '没有意向',
        'one' => '1 条意向',
        'many' => ':count 条意向',
    ],
];
