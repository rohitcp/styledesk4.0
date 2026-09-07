<?php

declare(strict_types=1);

/*
| 应用设置首页：分组标题与模块卡片。
|
| 这是一层翻译，而不是重写——值若与英文含义不同，就等于借着新增语言的名义
| 改动了产品文案。
|
| config 保留自己的英文字面量作为最后的兜底，因此新增但未翻译的模块会显示
| 它自己的名称，而不是一个键名。
*/

return [
    'groups' => [
        'business_setup' => [
            'name' => '业务设置',
            'description' => '你是谁、在哪里经营，以及如何展示这家店。',
        ],
        'booking_operations' => [
            'name' => '预约与运营',
            'description' => '决定什么可以被预约、由谁预约、什么时候预约的规则。',
        ],
        'clients_experience' => [
            'name' => '客户与体验',
            'description' => '你的客户看到、填写和购买的内容。',
        ],
        'communication' => [
            'name' => '沟通',
            'description' => 'StyleDesk 发送什么、发给谁，以及措辞如何。',
        ],
        'finance' => [
            'name' => '财务',
            'description' => '收款，以及随之而来的一切。',
        ],
        'administration' => [
            'name' => '管理',
            'description' => '谁能做什么，以及你的数据会怎样处理。',
        ],
        'developer_integrations' => [
            'name' => '开发者与集成',
            'description' => '把 StyleDesk 连接到其他系统。',
        ],
    ],

    'modules' => [
        'business' => [
            'name' => '业务信息',
            'description' => '店铺名称、类型、联系方式和经营配置。',
        ],
        'locations' => [
            'name' => '门店',
            'description' => '分店、地址、门店负责人、营业时间和联系方式。',
        ],
        'business-hours' => [
            'name' => '营业时间',
            'description' => '营业与打烊时间、分段班次、节假日和临时歇业。',
        ],
        'branding' => [
            'name' => '品牌形象',
            'description' => '应用、邮件和收据中使用的标志、网站图标和品牌色。',
        ],
        'languages' => [
            'name' => '语言',
            'description' => '设置应用的主要语言，并选择团队可以使用的其他语言。',
        ],
        'currency' => [
            'name' => '货币',
            'description' => '你的主要货币，以及用于定价的其他货币。',
        ],
        'booking-rules' => [
            'name' => '预约规则',
            'description' => '时间间隔、提前预约期限、可预约时段和预约规则。',
        ],
        'services' => [
            'name' => '服务分类',
            'description' => '价目表的分类方式，包括哪些对外开放以及显示顺序。',
        ],
        'resources' => [
            'name' => '资源分类',
            'description' => '可预约资产的分类方式——椅位、房间、设备——包括哪些对外开放以及显示顺序。',
        ],
        'staff' => [
            'name' => '员工',
            'description' => '团队成员、所属门店、工作时间、可提供的服务和在职状态。',
        ],
        'calendar-scheduling' => [
            'name' => '日历与排期',
            'description' => '日历行为、排期默认值，以及预约的显示方式。',
        ],
        'cancellation-no-show' => [
            'name' => '取消与未到店',
            'description' => '取消政策与时限、费用，以及未到店的处理方式。',
        ],
        'clients' => [
            'name' => '客户',
            'description' => '客户默认设置、偏好，以及客户资料的配置方式。',
        ],
        'client-booking' => [
            'name' => '客户预约',
            'description' => '面向客户的预约体验，以及客户可以自助完成的操作。',
        ],
        'online-booking' => [
            'name' => '在线预约',
            'description' => '公开的可预约时间、页面行为和在线预约规则。',
        ],
        'forms' => [
            'name' => '表单',
            'description' => '登记表、同意书和咨询表，以及何时请客户填写。',
        ],
        'memberships' => [
            'name' => '会员',
            'description' => '会员等级、周期性权益，以及会员权益的使用方式。',
        ],
        'packages' => [
            'name' => '套餐',
            'description' => '组合服务、套餐的销售方式，以及次数的核销方式。',
        ],
        'gift-cards' => [
            'name' => '礼品卡',
            'description' => '礼品卡面额、有效期、核销规则和默认设置。',
        ],
        'loyalty-rewards' => [
            'name' => '积分与奖励',
            'description' => '积分、奖励、获取规则，以及客户如何兑换。',
        ],
        'notifications' => [
            'name' => '通知',
            'description' => '邮件与应用内通知的行为，以及哪些事件通知给谁。',
        ],
        'email-settings' => [
            'name' => '邮件设置',
            'description' => '发件人名称与地址、回复地址和邮件默认设置。',
        ],
        'email-templates' => [
            'name' => '邮件模板',
            'description' => '确认、提醒、取消、改期、邀请和欢迎邮件。',
        ],
        'sms-settings' => [
            'name' => '短信设置',
            'description' => '短信发送方、消息默认设置，以及何时发送短信。',
        ],
        'payments' => [
            'name' => '支付',
            'description' => '接受的支付方式、订金、支付行为和默认设置。',
        ],
        'taxes' => [
            'name' => '税费',
            'description' => '税率、适用范围和税务相关的默认设置。',
        ],
        'tips' => [
            'name' => '小费',
            'description' => '小费选项、建议比例，以及小费的分配方式。',
        ],
        'receipts-invoices' => [
            'name' => '收据与发票',
            'description' => '编号、格式，以及收据和发票上显示的内容。',
        ],
        'inventory' => [
            'name' => '库存',
            'description' => '库存默认设置、低库存处理和零售商品设置。',
        ],
        'roles-permissions' => [
            'name' => '角色与权限',
            'description' => '控制店主、管理员、经理、前台、服务人员和自定义角色可以访问的内容。',
        ],
        'security' => [
            'name' => '安全',
            'description' => '会话行为、登录策略和应用安全设置。',
        ],
        'data-privacy' => [
            'name' => '数据与隐私',
            'description' => '数据保留、客户同意和隐私配置。',
        ],
        'import-export' => [
            'name' => '导入与导出',
            'description' => '导入数据、导出数据并执行迁移。',
        ],
        'system-preferences' => [
            'name' => '系统偏好',
            'description' => 'StyleDesk 的通用行为和全应用默认设置。',
        ],
        'integrations' => [
            'name' => '集成',
            'description' => '第三方集成与已连接的服务。',
        ],
        'api-webhooks' => [
            'name' => 'API 与 Webhook',
            'description' => 'API 访问、密钥、Webhook 端点和开发者集成。',
        ],
    ],

    /*
     * 模块状态标签。
     *
     * 以 config/app_settings.php 中的状态为键，因此新增状态只需在这里加一个键，
     * 而不必在视图里加分支。
     */
    'statuses' => [
        'active' => '已启用',
        'setup-required' => '需要设置',
        'coming-soon' => '即将推出',
        'view-only' => '仅查看',
    ],

    /*
     * 卡片下方的实时数字。
     *
     * 使用 Laravel 的 | 语法而不是 Str::plural()，后者只懂英文。
     * 中文没有复数变化，因此各分支写法相同，但整句仍由语言文件决定。
     */
    'counts' => [
        'active_staff' => '{1} :count 名在职成员|[2,*] :count 名在职成员',
        'pending_invites' => '{1} :count 个待接受的邀请|[2,*] :count 个待接受的邀请',
        'roles' => '{1} :count 个角色|[2,*] :count 个角色',
        'active_locations' => '{1} :count 家营业门店|[2,*] :count 家营业门店',
        'upcoming_closures' => '{1} :count 个即将到来的歇业|[2,*] :count 个即将到来的歇业',
        'enabled_currencies' => '{1} :count 种货币|[2,*] :count 种货币',
        'enabled_languages' => '{1} :count 种语言|[2,*] :count 种语言',
    ],
];
