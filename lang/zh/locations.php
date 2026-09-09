<?php

declare(strict_types=1);

/*
| 门店模块：卡片网格、查看页面，以及添加／编辑表单。
|
| 字段标签在三者之间刻意共用。"门店编号"在卡片上叫一个名字、在表单里叫另一个，
| 正是同一个键要防止的偏差。
*/

return [
    'title' => '门店',
    'intro' => '打开一家门店，查看它的负责人、营业时间、服务、员工和预约设置。',
    'summary' => '[1,*] :count 家营业中的门店',
    'summary_inactive' => ':count 家已停用',

    'add' => '添加门店',
    'add_first' => '添加你的第一家门店',
    'add_title' => '添加门店',
    'add_intro' => '这家分店的地址、联系方式、负责人和营业时间。服务、员工分配和预约规则会在门店创建之后再配置。',
    'edit' => '编辑门店',
    'edit_title' => '编辑门店',
    'view' => '查看门店',
    'deactivate' => '停用门店',
    'activate' => '启用门店',

    'created' => '门店已创建。',
    'saved' => '门店已保存。',
    'save_failed' => '无法保存你的更改。请检查填写的信息后重试。',
    'correct_fields' => '请修正高亮显示的字段后重试。',
    'made_inactive' => ':name 已停用。它不再接受新的预约；历史记录保持不变。',
    'made_active' => ':name 已重新启用。',

    'search_placeholder' => '按名称、编号、城市或地址搜索',
    'search_label' => '搜索门店',
    'all_statuses' => '全部状态',
    'actions_for' => '对 :name 的操作',

    'empty_title' => '还没有门店。',
    'empty_body' => '把你的门店添加进来，员工、服务和预约才有归属。',
    'no_matches' => '没有符合搜索条件的门店。',
    'no_matches_hint' => '换一个词，或清除筛选条件。',
    'clear_search' => '清除搜索',

    'inactive_notice' => '这家门店已停用。它不再接受新的预约，也不会出现在在线预约中。过往的预约、交易和员工记录保持不变。',

    'cards' => [
        'information' => '门店信息',
        'address' => '地址',
        'contact' => '联系方式',
        'contact_hint' => '凡是提到这家分店的地方，都用它来代替商户的主要联系方式。',
        'manager' => '门店负责人',
        'manager_hint' => '谁对这家分店负责。在这里指定某个人，并不会改变他们在 StyleDesk 里能做什么——那由"角色与权限"下的角色决定。',
        'manager_hint_short' => '谁对这家分店负责。这不会改变他们在 StyleDesk 里能做什么。',
        'hours' => '营业时间',
        'hours_hint' => '时间以这家门店自己的时区 :timezone 为准。',
        'elsewhere' => '在别处配置',
        'elsewhere_hint' => '在按门店单独设置上线之前，这些沿用全商户的设置。',
    ],

    'fields' => [
        'name' => '门店名称',
        'code' => '门店编号',
        'code_hint' => '一个简短的代号，用来在报表和收据上区分这家分店。',
        'type' => '门店类型',
        'primary' => '主要门店',
        'primary_hint' => '这家商户的主店。在这里设置会把它从当前的主店身上移除。',
        'status' => '状态',
        'status_hint' => '停用的门店不再接受新预约，也不会出现在在线预约中。历史记录会保留。',

        'address_line1' => '地址第 1 行',
        'address_line2' => '地址第 2 行',
        'suite' => '楼层／单元',
        'city' => '城市',
        'state' => '省／州',
        'postal_code' => '邮政编码',
        'country' => '国家／地区',
        'timezone' => '时区',
        'timezone_hint' => '这家分店的营业时间和预约都按这个时区解读。',

        'manager' => '门店负责人',
        'assistants' => '副负责人',
        'assistants_hint' => '已经在上面被选为门店负责人的人不会重复列出。',

        'phone' => '主要电话号码',
        'phone_short' => '主要电话',
        'phone_secondary' => '备用电话',
        'email' => '门店邮箱',
        'booking_email' => '预约联系邮箱',
        'support_email' => '客服邮箱',
        'website' => '网站',
        'extension' => '内部分机',
        'contact_person' => '联系人',

        'contact' => '联系方式',
        'today' => '今天',
    ],

    'placeholders' => [
        'name' => '市中心店',
        'code' => 'DT01',
        'type' => '未填写',
        'country' => '选择一个国家／地区',
        'timezone' => '选择一个时区',
        'manager' => '未指定',
    ],

    'not_assigned' => '未指定',
    'no_phone' => '没有电话号码',
    'closed' => '休息',
    'closed_today' => '今天休息',
    'no_active_staff' => '还没有在职员工。',
    'no_active_staff_link' => '添加一名员工',
    'no_active_staff_tail' => '之后就可以在这里指定负责人。',

    'elsewhere' => [
        'holidays' => '节假日与特殊营业时间',
        'holidays_value' => '沿用营业时间',
        'staff' => '已分配的员工',
        'staff_value' => '[1,*] 有 :count 名员工以此为主要门店',
        'services' => '提供的服务',
        'services_value' => '商户提供的全部服务',
        'resources' => '资源与房间',
        'resources_value' => '尚未配置',
        'booking' => '预约设置',
        'currency' => '货币与语言',
        'uses_business' => '使用商户设置',
        'link_hours' => '营业时间 →',
        'link_staff' => '员工 →',
        'link_services' => '服务 →',
        'link_business' => '商户 →',
        'link_permissions' => '权限 →',
    ],

    'confirm' => [
        'deactivate' => '把 :name 设为停用？它将不再接受新的预约，也不会出现在在线预约中。过往的预约、交易和员工记录会保留。',
        'activate' => '把 :name 重新启用？它可以接受预约，并会出现在在线预约中。',
    ],

    'validation' => [
        'name_required' => '请填写门店名称。',
        'code_unique' => '另一家门店已经在使用这个编号。',
        'status_required' => '请选择这家门店是否启用。',
        'address_required' => '请填写地址第 1 行。',
        'city_required' => '请填写城市。',
        'state_required' => '请填写省或州。',
        'postal_required' => '请填写邮政编码。',
        'country_required' => '请选择一个国家／地区。',
        'country_in' => '请从列表中选择一个国家／地区。',
        'timezone_required' => '请选择一个时区。',
        'timezone_in' => '请从列表中选择一个时区。',
        'phone_required' => '请填写主要电话号码。',
        'email_required' => '请填写门店邮箱。',
        'email_invalid' => '请输入有效的电子邮箱地址。',
        'url_invalid' => '请输入有效的网址，需包含 https://',
        'closes_after_opens' => '打烊时间必须晚于营业时间。',
        'staff_invalid' => '请选择属于你自己的员工。',
    ],

    /*
     * 类型和状态列表，以及星期几。
     *
     * 键名就是存入数据库的值，因此下拉框和校验规则始终读取同一份列表，
     * 而各语言只决定这些选项叫什么。
     */
    'types' => [
        'salon' => '美发沙龙',
        'spa' => 'SPA',
        'barbershop' => '理发店',
        'clinic' => '诊所',
        'studio' => '工作室',
        'mobile' => '上门服务',
        'other' => '其他',
    ],

    'statuses' => [
        'active' => '营业中',
        'inactive' => '已停用',
    ],

    'weekdays' => [
        0 => '星期日',
        1 => '星期一',
        2 => '星期二',
        3 => '星期三',
        4 => '星期四',
        5 => '星期五',
        6 => '星期六',
    ],

    'hours_card' => '门店营业时间',
    'hours_card_hint' => '这家分店什么时候营业，按它自己的时区。如果某天中间要休息，可以再加一个时段。',

    /*
     * 营业时间组件自己的控件文案。
     *
     * 以 props 传入，而不是在组件内部读取：服务器知道读者的语言，一个自带一整套
     * 文案副本的 Vue 组件，会变成第二个需要翻译的地方。
     */
    'hours_editor' => [
        'copy_monday' => '把周一复制到周二至周五',
        'open' => '营业',
        'closed' => '休息',
        'closed_all_day' => '全天休息',
        'add_period' => '再加一个时段',
        'to' => '至',
        'remove_period' => '从 :day 移除这个时段',
        'opening_time' => ':day 的营业时间',
        'closing_time' => ':day 的打烊时间',
        'copied' => '已把周一的营业时间应用到周二至周五。',
    ],
];
