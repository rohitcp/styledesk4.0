<?php

declare(strict_types=1);

/*
| 会员积分与奖励。
|
| 两类读者，按顶层键分开：`settings` 是给决定这套方案如何运作的人看的，`client`
| 是给站在前台、面前正站着一位客户的人看的。后者必须简短——前台是在客户等着的时候
| 读它的。
*/

return [

    'title' => '积分与奖励',

    /* 余额可能发生的每一类事各一行。写的是发生了什么，而不是一个分类，因为它是在
       一个列表里、挨着日期和数字被读到的。 */
    'activities' => [
        'earned' => '获得积分',
        'redeemed' => '兑换奖励',
        'expired' => '积分过期',
        'refund_adjustment' => '退款调整',
        'cancellation_adjustment' => '取消调整',
        'manual_add' => '手动增加',
        'manual_deduct' => '手动扣减',
    ],

    /* 有人手动调整余额的原因。 */
    'reasons' => [
        'customer_service' => '客服补偿',
        'promotion' => '促销活动',
        'correction' => '更正',
        'duplicate' => '重复积分',
        'refund' => '退款调整',
        'other' => '其他',
    ],

    'purchases' => [
        'services' => '服务',
        'products' => '商品',
        'memberships' => '会员',
        'packages' => '套餐',
        'gift_cards' => '礼品卡',
        'tips' => '小费',
        'taxes' => '税费',
    ],

    'expiry' => [
        'never' => '永不过期',
        '6m' => '6 个月后',
        '12m' => '12 个月后',
        '24m' => '24 个月后',
    ],

    'notifications' => [
        'earned_email' => '获得积分邮件',
        'earned_sms' => '获得积分短信',
        'reward_email' => '奖励可用邮件',
        'reward_sms' => '奖励可用短信',
    ],

    /* ------------------------------------------------------- 应用设置 -- */

    'settings' => [
        'title' => '积分与奖励',
        'intro' => '一次到店能赚多少、一分积分能抵多少，以及谁可以使用它。',

        'enable' => '启用积分与奖励',
        'enable_hint' => '客户在已完成且已付款的预约上获得积分，可以在以后到店时使用。',
        'disabled_note' => '奖励已关闭。不再产生新的积分，也无法兑换——所有余额和每一条历史记录都原样保留。',

        'program' => '方案',
        'program_hint' => '你的客户在收据和邮件里看到的名称。',
        'program_name' => '方案名称',
        'program_name_hint' => '例如：焕采积分、美丽积分、健康奖励。',
        'description' => '描述',
        'description_hint' => '一句话说明这套方案。选填。',
        'description_placeholder' => '每次到店都能积分，可用于抵扣以后的服务。',

        'earn' => '获得积分',
        'earn_hint' => '消费如何变成积分。积分取整：按 $5 = 1 分算，一项 $17 的服务得 3 分。',
        'spend_amount' => '消费',
        'points_earned' => '积分',
        'earn_rule' => '消费 :symbol:amount = :points',
        'eligible' => '可积分的消费',
        'eligible_hint' => '哪些消费能积分。服务始终可以——预约本来就是由服务构成的。',
        'always_on' => '始终开启',
        'coming_soon' => '即将推出',

        'redeem' => '使用积分',
        'redeem_hint' => '一分积分能抵多少，以及一次性使用余额的限制。',
        'points_required' => '所需积分',
        'reward_value' => '奖励价值',
        'minimum_redemption' => '最低可兑换积分',
        'minimum_hint' => '能够被使用的最小余额。低于它时，会告诉客户还差多少。',
        'maximum_reward' => '每笔交易的最高抵扣',
        'maximum_hint' => '一次到店最多可以抵扣多少。留空表示不设上限。',
        'rule' => ':points 分 = :value',

        'expiry' => '有效期',
        'expiry_hint' => '一分积分在赚到之后能存活多久。已经赚到的积分保留它们当时被赋予的期限。',

        'notifications' => '通知',
        'notifications_hint' => '余额变动时客户会收到什么。目前还不会发送——这些消息随下一个版本一起到来。',

        'rules' => '业务规则',
        'rules_hint' => '这套方案中由 StyleDesk 决定的部分，让你知道会发生什么。',
        'rule_locations' => '整个商户共用一个余额',
        'rule_locations_body' => '客户在任何一家门店都能积分，也能在任何另一家使用。不存在按分店分开的余额。',
        'rule_awarded' => '积分在到店完成且付款后入账',
        'rule_awarded_body' => '草稿、意向、取消、拒绝、爽约和未付款的预约都不产生积分。部分付款的预约，按已结清的比例积分。',
        'rule_refunds' => '退款会收回积分',
        'rule_refunds_body' => '全额退款会撤销这次到店赚到的全部积分。部分退款按同样的比例撤销，其余客户保留。',
        'rule_calculation' => '积分按客户实际支付的金额计算',
        'rule_calculation_body' => '优惠券、折扣和已使用的奖励都会先扣除，然后再计算积分。',

        'saved' => '积分设置已保存。',
    ],

    /* ----------------------------------------------------- 客户档案 -- */

    'client' => [
        'title' => '积分与奖励',
        'off' => '这家商户的奖励功能已关闭。',
        'off_hint' => '余额和历史记录都会保留。在方案重新开启之前，不会产生新的积分，也无法兑换。',

        'available' => '可用积分',
        'available_hint' => '现在就可以兑换的积分。',
        'pending' => '待入账积分',
        'pending_hint' => '来自尚未完成的预约的预计积分。',
        'lifetime_earned' => '累计获得',
        'lifetime_redeemed' => '累计使用',

        'reward_value' => '奖励价值',
        'worth' => '价值 :value',
        'worth_nothing' => '还不够兑换',

        'next_reward' => '下一个奖励',
        'progress' => ':have / :need 分',
        'to_go' => '再攒 :points 分即可解锁 :value',
        'unlocked' => ':value 可以兑换了',

        'activity' => '积分动态',
        'none' => '还没有积分动态。',
        'none_filtered' => '历史记录的这一部分没有内容。',
        'columns' => [
            'date' => '日期',
            'activity' => '动态',
            'booking' => '预约',
            'location' => '门店',
            'points' => '积分',
            'balance' => '余额',
        ],
        'filters' => [
            'all' => '全部动态',
            'earned' => '获得',
            'redeemed' => '使用',
            'adjustments' => '调整',
            'expired' => '过期',
            'refunds' => '退款',
        ],

        'adjust' => '调整积分',
        'adjust_title' => '调整积分',
        'adjust_intro' => '手动增加或扣减的积分会记在你的名下，事后无法修改。',
        'direction' => '调整类型',
        'add' => '增加积分',
        'remove' => '扣减积分',
        'points' => '积分',
        'reason' => '原因',
        'note' => '内部备注',
        'note_hint' => '只有你的团队看得到。选填。',
        'save' => '保存调整',
        'adjusted' => '积分已调整。',

        'by' => '由 :name',
        'expires' => ':date 过期',
    ],

    'activity' => [
        'system' => 'StyleDesk',
    ],

];
