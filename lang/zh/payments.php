<?php

declare(strict_types=1);

return [

    'title' => '收款',
    'intro' => '你是否收款、由谁处理，以及你接受哪些方式。',

    'enable' => '启用收款',
    'enable_hint' => '关闭时，StyleDesk 不会为预约记录任何金额，收银台也会隐藏。',

    'processor' => '支付服务商',
    'processor_hint' => '你的银行卡收款由一家服务商处理。无论选择哪一家，你仍然可以登记现金和转账。',
    'active' => '使用中',
    'coming_soon' => '即将推出',
    'use_this' => '使用这个',

    'gateways' => [
        'manual' => [
            'name' => '仅登记收款',
            'description' => '通过其他方式收到的钱——现金、银行转账，或用你自己的刷卡机收的卡。StyleDesk 只记下钱已收到；它不会向任何人扣款。',
            'unavailable' => '',
        ],
        'stripe' => [
            'name' => 'Stripe',
            'description' => '在线和在店里收取银行卡、Apple Pay 和 Google Pay 付款，款项打到你自己的银行账户。',
            'unavailable' => '',
        ],
        'square' => [
            'name' => 'Square',
            'description' => '连接你已经在用的 Square 账号，包括现有的读卡器和收银终端。',
            'unavailable' => '尚未上线。StyleDesk 正在开发中。',
        ],
    ],

    'stripe' => [
        'connect' => '连接 Stripe',
        'continue' => '继续设置',
        'manage' => '管理账号',
        'disconnect' => '断开连接',
        'not_connected' => '尚未连接 Stripe 账号。',
        'no_account' => '没有可打开的 Stripe 账号。',
        'payout_account' => '款项打到 •••• :last4',
        'no_payout_account' => '尚未设置收款银行账户',
        'connected' => 'Stripe 已连接。现在可以收取银行卡付款了。',
        'still_needed' => 'Stripe 还需要补充一些资料，你才能开始收款。',
        'disconnected' => 'Stripe 已断开连接。你仍然可以登记现金和转账。',
        'failed' => '无法连接到 Stripe。:reason',
        'not_ready' => '这个商户还不能收取银行卡付款。',
        'outstanding' => 'Stripe 还需要：:fields',
        'refunded_at_stripe' => '已在 Stripe 后台退款。',
        'modes' => [
            'platform' => '通过 StyleDesk 连接',
            'own' => '你自己的 Stripe 账号',
        ],
        'use_own' => '改用我自己的 Stripe 账号',
        'replace_keys' => '更换我的 Stripe 密钥',
        'use_own_hint' => '已经有 Stripe 了？粘贴你的密钥，StyleDesk 就会直接使用你的账号。收款、结算和争议处理完全在你和 Stripe 之间进行。',
        'secret_key' => '私钥（Secret key）',
        'publishable_key' => '公钥（Publishable key）',
        'save_keys' => '保存并验证',
        'keys_saved' => '你的 Stripe 密钥已保存并通过验证。',
        'key_rejected' => 'Stripe 不接受该密钥。:reason',
        'key_empty' => '没有填写密钥。',
        'key_warning' => '私钥可以在你的 Stripe 账号上扣款、退款并读取全部内容。StyleDesk 会加密保存并且不再显示它——请像对待密码一样对待它；如果你想限制 StyleDesk 的权限，请使用受限密钥。',
        'platform_not_configured' => 'StyleDesk 尚未配置为可代为开通 Stripe 账号。',
        'platform_unavailable' => '本次部署不支持通过 StyleDesk 连接。你仍然可以在下方使用自己的 Stripe 账号。',
        'statuses' => [
            'connected' => '已连接',
            'needs_attention' => '需要处理',
            'incomplete' => '设置未完成',
        ],
        /* 只说一次，因为这是店主最想知道的一点，也是当初选择 Connect 而不是
           共用一个商户账号的原因。 */
        'money_note' => '款项直接进入你自己的 Stripe 账号和你自己的银行。StyleDesk 从不代持你的资金。',
    ],

    'methods' => '接受的付款方式',
    'methods_hint' => '你的团队在收银时可以选择哪些方式。其中一部分由你的支付服务商处理；其余的是通过别的途径收到、在这里登记的。',
    'needs_processor' => '需要先连接支付服务商',

    'method_names' => [
        'card' => '信用卡／借记卡',
        'cash' => '现金',
        'apple_pay' => 'Apple Pay',
        'google_pay' => 'Google Pay',
        'gift_card' => '礼品卡',
        'store_credit' => '储值余额',
        'paypal' => 'PayPal',
        'zelle' => 'Zelle',
        'venmo' => 'Venmo',
        'cash-app' => 'Cash App',
        'external' => '其他／外部收款',
    ],

    'deposit' => '预约定金',
    'deposit_hint' => '接受预约时你要求先付的金额。',
    'deposit_type' => '定金',
    'deposit_value' => '金额',
    'deposit_types' => [
        'none' => '不收定金',
        'fixed' => '固定金额',
        'percent' => '按预约金额的百分比',
    ],
    /* 三个层级，只说一次。一项服务可以要求自己的定金，而单次预约可以覆盖前两者；
       不知道这个顺序的店主，会不明白服务为什么忽略了这里的设置。 */
    'deposit_levels' => '这是默认值。服务可以要求自己的定金，单次预约又可以覆盖这两者。',

    'save' => '保存',
    'saved' => '你的收款设置已保存。',
];
