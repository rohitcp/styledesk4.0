<?php

declare(strict_types=1);

return [

    'title' => '邮件',
    'intro' => '从 StyleDesk 给你的客户发送邮件。',

    'settings' => [
        'enable' => '启用客户邮件',
        'enable_hint' => '关闭时，客户档案上不会出现"发送邮件"。邮件历史仍留在原处。',
        'enabled' => '已启用',
        'disabled' => '已关闭',

        'default_method' => '默认发送方式',
        'default_hint' => '由一家服务商负责发送你的客户邮件。你随时可以更换。',
        'active' => '使用中',
        'coming_soon' => '即将推出',
        'use_this' => '使用这个',

        'sender' => '发件人',
        'sender_hint' => '无论由哪种方式发送，你的邮件都以这个身份署名。',
        'sender_name' => '发件人名称',
        'sender_name_hint' => '你的客户看到的名字。默认使用你的门店名称。',
        'reply_to' => '回复邮箱',
        'reply_to_hint' => '客户的回复会发到哪里。不填的话，回复不会到达任何人。',
        'reply_to_gmail' => 'Gmail 负责发送时不使用此项——回复会直接进入你已连接的收件箱。',
        'preview' => '你的客户会看到',

        'send_test' => '发送测试邮件',
        'test_hint' => '发到你自己的地址，这样你能看到客户收到的完全是什么样子。',
        'test_sent' => '测试邮件已发送至 :email。',
        'test_failed' => '测试邮件发送失败。:reason',
        'test_subject' => '来自 StyleDesk 的测试邮件',
        'test_body' => "这是一封来自 :name 的测试邮件。\n\n如果你能读到这段文字，说明你的客户邮件已经设置好并且可以正常工作。没有向你的任何客户发送过内容。",

        'saved' => '你的邮件设置已保存。',
        'save' => '保存',
    ],

    'providers' => [
        'styledesk' => [
            'name' => 'StyleDesk 邮件',
            'description' => '直接通过 StyleDesk 发送邮件，无需连接外部邮箱账号。',
        ],
        'gmail' => [
            'name' => '连接 Gmail',
            'description' => '连接你的企业 Gmail 或 Google Workspace 账号，用你现有的企业邮箱地址发送邮件。',
        ],
    ],

    'connection' => [
        'connected' => '已连接',
        'disconnected' => '已断开',
        'needs_attention' => '需要处理',
    ],

    'send' => [
        'action' => '发送邮件',
        'title' => '发送邮件',
        'to' => '收件人',
        'from' => '发件人',
        /* 客户在收件箱里实际看到的样子。在设置页面上也直说，因为本来期待用自己
           地址的店主，应该在这里知道，而不是从客户那里知道。 */
        'from_via' => ':name（通过 StyleDesk）',
        'template' => '邮件模板',
        'no_template' => '不使用模板',
        'related_booking' => '相关预约',
        'no_booking' => '无',
        'subject' => '主题',
        'message' => '正文',
        'cancel' => '取消',
        'submit' => '发送邮件',
        'sending' => '正在发送…',
        'sent' => '邮件已发送给 :name',
    ],

    'statuses' => [
        'queued' => '排队中',
        'sent' => '已发送',
        'delivered' => '已送达',
        'failed' => '发送失败',
    ],

    'history' => [
        'title' => '邮件历史',
        'empty' => '还没有给这位客户发送过邮件。',
        'sent_by' => '由 :name 发送',
        'system' => 'StyleDesk 系统',
        'view' => '查看',
    ],

    'errors' => [
        'disabled' => '这家门店的客户邮件功能已关闭。请到"应用设置 → 邮件"里打开。',
        'no_provider' => '尚未设置发送方式。请到"应用设置 → 邮件"里选择一个。',
        'no_address' => '这位客户档案里没有电子邮箱。',
        'failed' => '邮件发送失败。它已记在客户记录上并标记为失败，你可以重试。',
        'gmail_not_connected' => '尚未连接 Gmail 账号。请到"应用设置 → 邮件"里连接一个。',
        'reconnect_gmail' => '重新连接 Gmail',
    ],

    /*
    | 一封邮件可以从哪些模板开始。
    |
    | 它们是脚手架，不是信封：抽屉里放进去一份，发送者再修改，最终存下来的是真正
    | 发出去的那一版。
    */
    'templates' => [
        'appointment_follow_up' => [
            'name' => '服务后回访',
            'subject' => '感谢你光临 {{business_name}}',
            'body' => "你好 {{client_first_name}}：\n\n感谢你在 {{booking_date}} 到店。希望你对这次的 {{service_name}} 满意。\n\n如果有任何想调整的地方，直接回复这封邮件就好，我们会帮你处理。\n\n谢谢，\n{{business_name}}",
        ],
        'appointment_information' => [
            'name' => '预约信息',
            'subject' => '你在 {{business_name}} 的预约',
            'body' => "你好 {{client_first_name}}：\n\n这是关于你在 {{booking_date}} {{booking_time}} 由 {{staff_name}} 服务的预约的提醒。\n\n预约编号：{{booking_reference}}\n\n如需更改任何内容，请回复这封邮件或打电话给我们。\n\n谢谢，\n{{business_name}}",
        ],
        'payment_reminder' => [
            'name' => '付款提醒',
            'subject' => '关于你在 {{business_name}} 的余额提醒',
            'body' => "你好 {{client_first_name}}：\n\n温馨提醒，你目前的未结余额是 {{balance_due}}。\n\n可以在下次到店时结清，或回复这封邮件，我们会给你发一个付款链接。\n\n谢谢，\n{{business_name}}",
        ],
        'outstanding_balance' => [
            'name' => '未结余额',
            'subject' => '你在 {{booking_date}} 的到店尚有未结余额',
            'body' => "你好 {{client_first_name}}：\n\n你在 {{booking_date}} 的到店尚有 {{balance_due}} 的未结余额。\n\n预约编号：{{booking_reference}}\n\n如果你认为这有误，请回复，我们会立刻核对。\n\n谢谢，\n{{business_name}}",
        ],
        'thank_you' => [
            'name' => '感谢',
            'subject' => '来自 {{business_name}} 的感谢',
            'body' => "你好 {{client_first_name}}：\n\n谢谢你选择 {{business_name}}，很高兴为你服务。\n\n期待很快再见到你。\n\n谢谢，\n{{business_name}}",
        ],
        'service_follow_up' => [
            'name' => '服务回访',
            'subject' => '你的 {{service_name}} 现在怎么样？',
            'body' => "你好 {{client_first_name}}：\n\n距离 {{staff_name}} 为你做的 {{service_name}} 已经有一阵子了，我们想问问你用得还习惯吗。\n\n如果想做个补修，或者有任何疑问，回复这封邮件就好。\n\n谢谢，\n{{business_name}}",
        ],
        'membership_information' => [
            'name' => '会员信息',
            'subject' => '{{business_name}} 的会员',
            'body' => "你好 {{client_first_name}}：\n\n我们想你也许会想了解一下我们的会员方案——常来的客户可以享受更优惠的价格和优先预约。\n\n回复这封邮件，我们把详细内容发给你。\n\n谢谢，\n{{business_name}}",
        ],
        'general_message' => [
            'name' => '普通消息',
            'subject' => '来自 {{business_name}} 的消息',
            'body' => "你好 {{client_first_name}}：\n\n\n谢谢，\n{{business_name}}",
        ],
    ],

    'gmail' => [
        'connect' => '连接 Gmail',
        'reconnect' => '重新连接',
        'disconnect' => '断开连接',
        'connected' => 'Gmail 已连接。你的客户邮件现在从 :email 发出。',
        'connected_on' => '连接于 :date',
        'disconnected' => 'Gmail 已断开连接。客户邮件现在通过 StyleDesk 邮件发送。',
        'failed' => 'Gmail 连接失败。:reason',
        'state_mismatch' => '无法验证这次连接尝试，请重试。',
        'no_code' => 'Google 没有返回任何可用于连接的内容。',
        'no_refresh_token' => 'Google 没有为这个账号签发长期授权。',
        'reconnect_needed' => 'Gmail 连接已失效，需要重新连接。',
        'unknown_error' => 'Google 没有说明原因。',
        'replies_note' => '客户会直接回复到这个收件箱。回复不会被带回 StyleDesk。',
    ],

    'variables' => [
        'title' => '你可以使用',
        'hint' => '加载模板时，这些会被替换成实际内容。',
    ],
];
