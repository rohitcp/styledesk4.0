<?php

declare(strict_types=1);

/*
| 商户设置模块：只读页面和它的编辑表单。
|
| 字段标签在两个页面之间刻意共用。"主要邮箱"在查看页上叫一个名字、在表单里叫
| 另一个，正是同一个键要防止的偏差。
*/

return [
    'title' => '商户',
    'intro' => '商户名称、类型、联系方式和经营配置。',
    'edit_title' => '编辑商户',
    'edit_intro' => '更新你的商户信息和经营细节。门店、营业时间、货币和预约规则各有自己的设置页面。',
    'saved' => '商户设置已更新。',
    'save_failed' => '目前无法保存你的更改，请重试。',
    'correct_fields' => '请修正高亮显示的字段后重试。',

    'cards' => [
        'information' => '商户信息',
        'contact' => '联系方式',
        'address' => '商户地址',
        'address_hint' => '你的主要地址。共 :count 家门店。',
        'regional' => '区域设置',
        'branding' => '标识与品牌',
        'branding_hint' => '在「品牌形象」中配置；这里显示出来作为参考。',
        'languages' => '语言',
        'languages_hint' => '在「语言」中配置；这里显示出来作为参考。',
        'currency' => '货币',
        'currency_hint' => '在「货币」中配置；这里显示出来作为参考。',
        'online_booking' => '在线预约',
        'online_booking_hint' => '注册时设定。「在线预约」模块即将推出。',
        'security' => '安全',
        'defaults' => '商户默认值',
        'defaults_hint' => '新预约和新服务的起始设置。',
        'presence' => '商户对外形象',
        'presence_hint' => '客户在 StyleDesk 之外能在哪里找到你。',
        'advanced' => '高级信息',
        'payments' => '收款方式',
        'payments_hint' => '客户被要求把钱转到哪些账号。这些会在收银时念给客户听，所以完全按你写的样子保留。',
    ],

    'fields' => [
        'name' => '商户名称',
        'legal_name' => '法定名称',
        'business_type' => '商户类型',
        'category' => '类别／专长',
        'description' => '描述',
        'logo' => '商户标志',
        'status' => '状态',

        'business_email' => '主要邮箱',
        'business_phone' => '主要电话',
        'support_email' => '客服邮箱',
        'booking_email' => '预约联系邮箱',
        'website' => '网站',
        'website_scheme' => '网址协议',

        'address_line1' => '地址第 1 行',
        'address_line2' => '地址第 2 行',
        'city' => '城市',
        'state' => '省／州',
        'postal_code' => '邮政编码',
        'country' => '国家／地区',
        'address' => '地址',

        'primary_language' => '主要语言',
        'secondary_languages' => '其他语言',
        'primary_currency' => '主要货币',
        'secondary_currencies' => '其他货币',
        'timezone' => '时区',
        'date_format' => '日期格式',
        'time_format' => '时间格式',
        'first_day_of_week' => '一周的第一天',

        'default_location' => '默认门店',
        'default_booking_duration' => '默认预约时长',
        'default_appointment_interval' => '默认预约间隔',
        'default_tax_behavior' => '默认税费处理方式',
        'default_tax_rate' => '税率',
        'paypal_handle' => 'PayPal',
        'zelle_handle' => 'Zelle',
        'cash_app_handle' => 'Cash App',
        'venmo_handle' => 'Venmo',
        'session_timeout' => '会话超时',
        'default_staff_assignment' => '默认员工分配方式',
        'allow_online_booking' => '允许在线预约',
        'guest_booking' => '已开启免注册预约',

        'business_id' => '商户编号',
        'booking_address' => '预约页面地址',

        'instagram' => 'Instagram',
        'facebook' => 'Facebook',
        'tiktok' => 'TikTok',
        'google_business' => 'Google 商家资料',
    ],

    'manage' => '管理',
    'manage_branding' => '品牌样式 →',
    'enabled' => '已启用',
    'disabled' => '已关闭',

    /*
     * 校验提示。
     *
     * 写成指令，而不是对规则的描述。"商户邮箱字段必须是有效的电子邮件地址"说的是
     * 校验器；"请输入有效的电子邮箱地址"说的是该怎么做，而它就显示在对应输入框的
     * 旁边。
     */
    'validation' => [
        'name_required' => '请填写商户名称。',
        'email_required' => '请填写主要邮箱。',
        'email_invalid' => '请输入有效的电子邮箱地址。',
        'url_invalid' => '请输入有效的网址，需包含 https://',
        'instagram_invalid' => '请输入有效的 Instagram 网址，需包含 https://',
        'facebook_invalid' => '请输入有效的 Facebook 网址，需包含 https://',
        'tiktok_invalid' => '请输入有效的 TikTok 网址，需包含 https://',
        'google_invalid' => '请输入有效的 Google 商家网址，需包含 https://',
        'status_required' => '请选择这家商户是否启用。',
        'location_invalid' => '请选择属于你自己的门店。',
    ],

    /*
     * 下拉框里的取值，而不只是它们的标签。
     *
     * 依据"这一做法同样适用于下拉框的取值"——一个标签是中文、选项却是英文的表单，
     * 只是把同一个半翻译的页面往里挪了一层。
     *
     * 日期和时间*格式*的名称保持原样："DD/MM/YYYY"是一个模式，不是一句话，把这些
     * 字母翻译过来，描述的会是一个没人在用的格式。
     */
    'first_day_of_week' => [
        '0' => '星期日',
        '1' => '星期一',
        '6' => '星期六',
    ],

    'time_formats' => [
        '12' => '12 小时制（1:30 PM）',
        '24' => '24 小时制（13:30）',
    ],

    'durations' => [
        'minutes' => '[1,*] :count 分钟',
        'hour' => '1 小时',
        'hour_thirty' => '1 小时 30 分钟',
        'hours' => ':count 小时',
    ],

    'tax_behaviors' => [
        'inclusive' => '价格已含税',
        'exclusive' => '结账时另加税',
        'none' => '不收税',
    ],

    'staff_assignment' => [
        'any' => '任意有空的员工',
        'client-chooses' => '由客户选择员工',
        'manual' => '由商户手动分配',
    ],

    'hints' => [
        'tax_rate' => '一个百分比。用于需要收税的预约总额。',
        'inactive' => '停用的商户不会出现在对外的预约页面上。',
        'session_timeout' => '在一段时间没有操作后自动退出用户的登录。',
        'interval' => '预约开始时间会对齐到的网格。',
        'regional' => '语言、货币和时区在各自的模块中设置。',
    ],

    'placeholders' => [
        'legal_name' => '如果与经营名称不同，请填注册名称',
        'category' => '卷发专家',
        'description' => '一句会出现在你的预约页面上、给客户读的话。',
        'paypal_handle' => 'paypal.me/yoursalon',
        'zelle_handle' => 'pay@yoursalon.com',
        'cash_app_handle' => '$yoursalon',
        'venmo_handle' => '@yoursalon',
    ],

    'choose' => [
        'date_format' => '选择一个日期格式',
        'time_format' => '选择一个时间格式',
        'day' => '选择一天',
        'duration' => '选择一个时长',
        'interval' => '选择一个间隔',
        'tax' => '选择税费处理方式',
        'session_timeout' => '选择一个超时时长',
        'assignment' => '选择一条分配规则',
    ],

    /*
     * 商户类型列表。
     *
     * 这是 StyleDesk 自己的参考数据，按填充器分配的 slug 索引——不是商户自己写的
     * 东西，所以由我们来翻译。凡是商户自己写下的，都保留它自己的说法。
     *
     * 数据库里的名称是兜底值，因此由后来的填充器新增、还没有翻译的类型，会显示为
     * 它本来的名字。
     */
    'types' => [
        'hair-salon' => '美发沙龙',
        'barber-shop' => '理发店',
        'nail-salon' => '美甲店',
        'spa' => 'SPA',
        'massage' => '按摩',
        'med-spa' => '医美中心',
        'esthetics' => '美容护理',
        'eyebrows-lashes' => '美眉与美睫',
        'makeup-studio' => '化妆工作室',
        'tattoo-studio' => '纹身工作室',
        'wellness' => '养生',
        'fitness' => '健身',
        'other' => '其他',
    ],

    'session_timeouts' => [
        15 => '15 分钟',
        30 => '30 分钟（推荐）',
        60 => '1 小时',
        120 => '2 小时',
        240 => '4 小时',
        480 => '8 小时',
    ],
];
