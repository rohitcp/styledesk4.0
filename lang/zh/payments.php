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

    /* Card brands, as a person writes them rather than as a gateway keys
       them. Anything not listed falls back to its own key, tidied up — a new
       brand should read as itself rather than as nothing. */
    'methods_list' => [
        'no_vault' => '尚未连接支付服务商，无法保存银行卡。',
        'default_set' => '已将 :card 设为默认付款方式。',
        'removed' => '已移除 :card。',
        'title' => '付款方式',
        'none' => '尚未保存银行卡',
        'none_hint' => '在此保存的银行卡可在客户不在场时用于会员续费扣款。',
        'default' => '默认',
        'make_default' => '设为默认',
        'expires' => ':date 到期',
        'expired' => '已过期',
        'expiring' => '本月到期',
        'needs_attention' => '付款方式需要处理',
        'add' => '添加银行卡',
        'remove' => '移除银行卡',
        'remove_confirm' => '确定移除该银行卡吗？之后将无法扣款。',
        'in_use' => '该卡正用于续费 :name。请先选择其他付款方式再移除。',
        'used_by' => '用于续费 :name',
        'gateway' => '由 :name 处理',
        'statuses' => [
            'active' => '有效',
            'expired' => '已过期',
            'removed' => '已移除',
        ],
    ],

    'brands' => [
        'visa' => 'Visa',
        'mastercard' => '万事达',
        'amex' => '美国运通',
        'discover' => 'Discover',
        'diners' => '大来卡',
        'jcb' => 'JCB',
        'unionpay' => '银联',
    ],

    /* The Stripe events StyleDesk received, and what it did about them. */
    /* What a business lets its payments do, and what each payment cost.
       A capability that needs a processor says so rather than offering a
       switch that would do nothing. */
    'capabilities' => [
        'title' => '付款可用功能',
        'hint' => '关闭本店不提供的功能。需要支付服务商的功能在连接之前保持关闭。',
        'groups' => [
            'in_person' => '线下付款',
            'online' => '线上付款',
        ],
        'items' => [
            'card' => '信用卡与借记卡',
            'manual_card_entry' => '手动输入卡号',
            'tap_to_pay' => 'Tap to Pay',
            'card_reader' => '读卡器',
            'booking_deposit' => '预约定金',
            'full_payment' => '全额付款',
            'payment_link' => '付款链接',
            'card_on_file' => '存档银行卡',
            'membership_payment' => '会员付款',
            'online_booking' => '线上预约付款',
            'invoice_payment' => '账单付款',
            'gift_card' => '购买礼品卡',
        ],
        'blocked' => [
            'unbuilt' => '即将推出',
            'no_processor' => '需要已连接的支付服务商',
        ],
    ],

    'fees' => [
        'title' => '手续费',
        'processor' => '处理手续费',
        'platform' => 'StyleDesk 费用',
        'net' => '净额',
        'unknown' => '结算中',
    ],

    'webhooks' => [
        'unhandled' => 'StyleDesk 不处理该类型的事件。',
    ],

    'stripe' => [
        'disputed' => '客户已提出争议（:reason）。',
        'disconnect_title' => '要断开 Stripe 吗？',
        'disconnect_warning' => '断开会影响线上预约、定金、会员续费、付款链接、已保存的银行卡以及未结余额。已有记录会保留，但在重新连接 Stripe 之前无法进行新的扣款。',
        'sandbox' => '沙盒',
        'live' => '正式',
        'sandbox_notice' => '当前为沙盒模式。此处的付款为测试交易，不会产生真实资金流动。',
        'provider_platform' => 'StyleDesk Stripe',
        'provider_own' => '你自己的 Stripe 账户',
        'not_settled' => '未扣款，该笔支付未完成结算。',
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
            'sandbox' => '沙盒',
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
