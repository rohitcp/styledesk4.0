<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| StyleDesk SMS
|--------------------------------------------------------------------------
|
| What the desk reads about a text message: where it got to, and what it was
| about.
|
*/

return [
    'title' => '短信',

    'statuses' => [
        'queued' => ['label' => '排队中'],
        'sending' => ['label' => '发送中'],
        'sent' => ['label' => '已发送'],
        'delivered' => ['label' => '已送达'],
        'failed' => ['label' => '失败'],
        'rejected' => ['label' => '已拒绝'],
        'expired' => ['label' => '已过期'],
        'opted_out' => ['label' => '已退订'],
    ],

    'types' => [
        'reply' => '客户回复',
        'test' => '测试短信',
        'booking_confirmation' => '预约确认',
        'appointment_reminder' => '预约提醒',
        'booking_rescheduled' => '预约已改期',
        'booking_cancelled' => '预约已取消',
        'birthday' => '生日祝福',
        'membership' => '会员通知',
    ],

    'registration' => [
        'not_started' => '未开始',
        'submitted' => '已提交',
        'pending' => '审核中',
        'approved' => '已批准',
        'rejected' => '已拒绝',
        'suspended' => '已暂停',
    ],

    'settings' => [
        'sending_from' => '发送号码：:number',
        'test' => '发送测试短信',
        'test_hint' => '按照与预约确认完全相同的流程发送一条短信，并像其他短信一样记录下来。',
        'test_to' => '发送至',
        'send_test' => '发送短信',
        'test_body' => '来自 :business 的测试短信。StyleDesk 短信功能正常。',
        'test_sent' => '测试短信已通过 :provider 发送至 :number。',
        'test_failed' => '测试短信发送失败。:reason',
        'test_unknown' => '服务商未说明原因。',
        'test_bad_number' => '这看起来不像电话号码。',
        'test_live' => '已连接 Telnyx，短信会真实送达手机并产生费用。',
        'test_local' => '尚未连接运营商，短信不会送达手机，仅写入日志。',
        'title' => '短信设置',
        'intro' => 'StyleDesk 会给客户发送哪些短信、使用哪个号码，以及你愿意为此支出多少。',
        'sender' => '短信号码',
        'sender_hint' => 'StyleDesk 代为申请号码及运营商登记。在登记获批前，无法向美国手机号发送短信。',
        'no_number' => '尚未分配',
        'registration' => '登记状态',
        'saved' => '短信设置已保存。',
        'enable' => '启用 StyleDesk 短信',
        'enable_hint' => '仅在开启时才会发送短信。',
        'disabled_note' => '短信已关闭。不会发送任何内容，也不再询问其他设置。',
        'messages' => '事务性短信',
        'messages_hint' => '哪些短信会发出。每一条都只发给已同意接收的客户。',
        'not_yet' => '尚未提供。',
        'reminders' => '预约提醒',
        'reminders_hint' => '提前多久发送提醒。可多选以发送多条。',
        'hours_before' => '{1} 提前 1 小时|[2,*] 提前 :count 小时',
        'birthday_at' => '生日祝福发送时间',
        'birthday_at_hint' => '按门店当地时间。',
        'spend' => '用量与上限',
        'spend_hint' => '设置上限，避免导入或误操作向全部客户发送短信。',
        'monthly_limit' => '每月短信上限',
        'no_limit' => '不限',
        'alert_at' => '达到以下比例时提醒',
        'used_this_month' => '本月短信数',
        'segments_this_month' => '本月计费条数',
    ],

    'errors' => [
        'disabled' => '本安装已关闭 StyleDesk 短信功能。',
        'no_sender' => '尚未设置发送号码。请在设置 → 短信中填写，或设置 TELNYX_FROM_NUMBER。',
    ],

    'confirmation' => [
        'not_requested' => '未请求',
        'pending' => '等待客户确认',
        'confirmed' => '客户已确认',
        'cancellation_requested' => '已申请取消',
        'needs_review' => '需人工处理',
    ],
];
