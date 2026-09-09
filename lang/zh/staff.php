<?php

declare(strict_types=1);

/*
| 员工模块：员工目录、个人档案，以及添加／编辑表单。
|
| 字段标签在三者之间刻意共用。"常用名"在档案上叫一个名字、在表单里叫另一个，
| 正是同一个键要防止的偏差。
*/

return [
    'title' => '员工',
    'summary' => '[1,*] :count 名在职员工',
    'summary_pending' => '[1,*] :count 份待接受的邀请',

    'add' => '添加员工',
    'add_title' => '添加员工',
    'add_intro' => '填写他们的资料和角色。工作时间、可预约情况和按服务的设置，会在记录建好之后再配置。',
    'adding' => '正在添加…',
    'save_and_add_another' => '保存并继续添加',
    'edit' => '编辑',
    'edit_person' => '编辑 :name',
    'edit_title' => '编辑员工',
    'view_profile' => '查看档案',

    'created' => ':name 已加入你的团队。',
    'created_invited_to' => ':name 已添加，邀请正在发往 :email。',
    'saved_person' => ':name 的资料已更新。',
    'add_failed' => '目前无法添加这名员工，请重试。',
    'created_invited' => ':name 已添加，邀请正在发送中。',
    'saved' => '员工资料已更新。',
    'deleted' => ':name 已从你的团队中移除。',
    'save_failed' => '无法保存你的更改。请检查填写的信息后重试。',
    'correct_fields' => '请修正高亮显示的字段后重试。',
    'delete_confirm' => '把 :name 从团队中移除？其记录将被删除；如果他们有登录账号，也将失去对本商户的访问权限。',

    /* 列表表格——与客户和服务列表使用同一套结构。 */
    'view' => '查看员工',
    'manage_schedule' => '管理排班',
    'set_time_off' => '设置休假',
    'activate' => '启用',
    'deactivate' => '停用',
    'deactivate_confirm' => '停用 :name？他们的记录、服务和房间都会保留，但不再出现在可预约的人选中。',
    'activated_person' => ':name 已恢复在职。',
    'deactivated_person' => ':name 已不再在职。',
    'add_schedule' => '添加员工排班',
    'results' => [
        'zero' => '未找到员工',
        'one' => '找到 1 名员工',
        'many' => '找到 :count 名员工',
        'empty' => '没有符合搜索或筛选条件的员工。',
        'clear' => '清除筛选',
    ],
    'showing' => '显示第 :from–:to 名员工，共 :total 名',
    'none_yet' => '还没有员工',
    'none_yet_hint' => '添加员工后，就能管理排班、服务、门店和可预约时段。',

    'search_placeholder' => '按姓名、邮箱、电话或职位搜索',
    'search_label' => '搜索员工',
    'apply_filters' => '应用筛选',

    'filters' => [
        'role' => '角色',
        'all_roles' => '全部角色',
        'location' => '门店',
        'all_locations' => '全部门店',
        'service' => '服务',
        'all_services' => '全部服务',
        'provider_type' => '人员类型',
        'all_provider_types' => '全部人员类型',
        'employment' => '用工形式',
        'all_employment' => '全部用工形式',
        'status' => '状态',
        'all_statuses' => '全部状态',
        'sort' => '排序方式',
    ],

    'columns' => [
        'name' => '姓名',
        'role' => '角色',
        'location' => '门店',
        'contact' => '联系方式',
        'services' => '服务',
        'status' => '状态',
        'last_login' => '最近登录',
        'actions' => '操作',
    ],

    'empty_title' => '没有符合这些筛选条件的员工。',
    'empty_hint' => '清除筛选条件，或从团队步骤中邀请成员。',
    'never' => '从未',
    'all_locations' => '全部门店',
    'actions_for' => '对 :name 的操作',

    'cards' => [
        'shift_rule' => '班次规则',
        'shift_rule_hint' => '为这名员工指定一条可复用的班次规则。生成其排班时会使用该规则。',
        'basic' => '基本信息',
        'contact' => '联系方式',
        'employment' => '角色与用工',
        'employment_hint' => '角色决定他们在 StyleDesk 里能做什么；用工形式是商户如何雇用他们——两者互不影响。',
        'account' => '账号',
    ],

    'fields' => [
        'shift_rule' => '班次规则',
        'no_shift_rule' => '未指定班次规则',
        'first_name' => '名字',
        'last_name' => '姓氏',
        'middle_name' => '中间名',
        'preferred_name' => '常用名',
        'preferred_name_placeholder' => '团队和客户如何称呼他们',
        'pronouns' => '人称代词',
        'job_title' => '职位',
        'job_title_placeholder' => '资深发型师',
        'employee_ref' => '员工编号',
        'employee_ref_hint' => '保存该成员时自动分配。',
        'avatar' => '头像',
        'avatar_hint' => 'JPG、PNG 或 WEBP，不超过 2 MB。会显示在预约页面和团队列表中。',
        'bio' => '简介',
        'bio_placeholder' => '如果他们接受预约，这段简介会显示在预约页面上。',

        'email' => '主要邮箱',
        'email_hint' => '邀请邮件也会发到这个地址。',
        'work_email' => '工作邮箱',
        'phone' => '主要电话',
        'phone_type' => '电话类型',
        'secondary_phone' => '备用电话',
        'emergency_contact_name' => '紧急联系人',
        'emergency_contact_phone' => '紧急联系电话',
        'emergency_contact_relationship' => '关系',
        'relationship_placeholder' => '伴侣',
        'address' => '地址',

        'role' => '角色',
        'role_placeholder' => '选择一个角色',
        'role_hint' => '你只能指派自己权限范围内的角色。',
        'location' => '主要门店',
        'employment_type' => '用工形式',
        'provider_type' => '人员类型',
        'specialities' => '专长',
        'services' => '他们提供的服务',
        'services_hint' => '一旦分配了服务，客户就可以指名预约这个人。',
        'services_placeholder' => '搜索或选择服务',
        'resources' => '他们使用的资源',
        'resources_hint' => '这个人使用的椅位、房间或工位。如果哪个都行，留空即可。',
        'resources_placeholder' => '搜索或选择资源',
        'date_of_birth' => '出生日期',
        'started_on' => '入职日期',

        'account_status' => '账号状态',
        'account_status_hint' => '停用的成员无法被预约，也不会接到新的预约。',
        'login_enabled' => '允许员工登录',
        'login_enabled_hint' => '他们会获得自己的 StyleDesk 账号。如果只需要出现在日历上，请保持关闭。',
        'send_invitation' => '立即发送邀请',
        'send_invitation_hint' => '通过邮件发给他们一个链接，用于设置密码并加入。你也可以稍后再发。',
        'invitation_message' => '留言',
        'invitation_message_placeholder' => '很高兴你加入我们团队。',
    ],

    'not_specified' => '未填写',

    'validation' => [
        'shift_rule_unavailable' => '所选门店不支持这条班次规则。请另选一条。',
        'first_name_required' => '请填写名字。',
        'last_name_required' => '请填写姓氏。',
        'email_required' => '请填写主要邮箱。',
        'email_invalid' => '请输入有效的电子邮箱地址。',
        'email_taken' => '你团队中已有人使用该邮箱地址。',
        'role_required' => '请为这个人选择一个角色。',
        'role_invalid' => '你只能指派自己权限范围内的角色。',
        'location_invalid' => '请选择属于你自己的门店。',
        'service_invalid' => '请选择属于你自己的服务。',
        'role_not_yours' => '你无法指派该角色。',
        'own_role' => '你不能更改自己的角色，请让另一位管理员来操作。',
        'avatar_max' => '头像不得超过 2 MB。',
        'avatar_mimes' => '请使用 JPG、PNG 或 WEBP 图片。',
    ],

    /*
     * 档案页面上的事实标签。
     *
     * 与 `fields` 分开，因为档案陈述某个值是什么（"法定姓名"），而表单询问它的
     * 各个部分（"名字"）；把两者合并，会让一个页面提出另一个页面正在回答的问题。
     */
    'profile' => [
        'about' => '概况',
        'legal_name' => '法定姓名',
        'preferred_name' => '常用名',
        'pronouns' => '人称代词',
        'employee_ref' => '员工编号',

        'contact' => '联系方式',
        'email' => '主要邮箱',
        'work_email' => '工作邮箱',
        'phone' => '主要电话',
        'secondary_phone' => '备用电话',
        'address' => '地址',
        'emergency_contact' => '紧急联系人',

        'access' => '角色与权限',
        'role' => '角色',
        'location' => '主要门店',
        'login' => '员工登录',
        'account' => '账号',
        'last_login' => '最近登录',

        'services' => '服务',
        'assign_services' => '分配服务',

        'employment' => '用工',
        'employment_type' => '用工形式',
        'provider_type' => '人员类型',
        'specialities' => '专长',
        'added' => '添加于',

        'invitation' => '邀请',
        'sent_to' => '发送至',

        'activity' => '动态',
        'activity_hint' => '对这条记录的管理性变更。',
        'activity_empty' => '暂无记录。',

        'bookable' => '可被预约',
        'no_login' => '无登录账号',
        'summary_email' => '邮箱',
        'summary_phone' => '电话',
        'summary_location' => '门店',
    ],

    'edit_staff' => '编辑员工',
    'more_actions' => '更多',
    'set_on_leave' => '设为休假中',
    'on_leave_person' => ':name 正在休假。',
    'services_added' => '[1,*] 已添加 :count 项服务。',
    'service_removed' => '已移除 :name。服务本身不受影响。',
    'shift_rule_assigned' => '已应用到 :name。',
    'shift_rule_cleared' => '班次规则已移除。',

    'tabs' => [
        'overview' => '概览',
        'schedule' => '排班',
        'services' => '服务',
        'notes' => '备注',
    ],

    /* 概览标签页上的报表。只呈现目前数据支持的内容——预约相关的数字需要预约模块，
       而一张读作"—"的卡片只会教会读者忽略这一行。 */
    'report' => [
        'shifts_this_week' => '本周班次',
        'hours_this_week' => '本周工时',
        'shifts_this_month' => '本月班次',
        'hours_this_month' => '本月工时',
        'upcoming_shifts' => '即将到来的班次',
        'services' => '服务',
    ],

    'schedule_tab' => [
        'shift_rule' => '班次规则',
        'current_rule' => '当前班次规则',
        'remove_rule' => '移除班次规则',
        'remove_rule_confirm' => '把 :rule 从 :name 身上移除？已经排入其日程的班次保持不变，移除的只是背后的规律。',
        'no_rule' => '未指定班次规则。',
        'assign_rule' => '指定班次规则',
        'change_rule' => '更换班次规则',
        'working_schedule' => '工作排班',
        'weeks' => '[1,*] :count 周',
        'previous' => '上一个',
        'today' => '今天',
        'next' => '下一个',
        'not_working' => '不上班',
    ],

    'services_tab' => [
        'title' => '他们可以执行的服务',
        'intro' => '这个人可以被预约做哪些事。移除其中一项，只是取消他们被预约做这件事的资格——服务本身不受影响。',
        'add' => '添加服务',
        'choose' => '要添加的服务',
        'remove' => '移除',
        'remove_confirm' => '把 :service 从 :name 身上移除？他们将不再能因此被预约。服务本身不受影响。',
        'none' => '未分配任何服务',
        'none_hint' => '在至少分配一项服务之前，:name 无法被指名预约。',
    ],

    'notes' => [
        'title' => '内部备注',
        'intro' => '商户就这个人保留的备注——排班、培训、可用情况。绝不会展示给客户，也不会出现在预约页面上。',
        'body' => '备注',
        'placeholder' => '团队需要知道的任何事。',
        'add' => '添加备注',
        'added' => '备注已添加。',
        'deleted' => '备注已删除。',
        'delete_confirm' => '删除这条备注？删除后无法找回。',
        'body_required' => '添加备注前请先写点内容。',
        'none' => '还没有备注。',
        'someone' => '某人',
    ],

    /*
    | 员工使用率——每个人可被预约的时间里，有多少已被约满。
    |
    | 措辞站在管理者的角度，而不是报表的角度："低于目标 3%"而不是"差异 -3"，
    | "未排班"而不是"0%"。没有被排班的人，本来就没有一天可以填。
    */
    'utilization' => [
        'title' => '员工使用率',
        'subtitle' => '团队可被预约的时间里有多少已被预约',
        'intro' => '使用率是按每个人实际排班接待客户的时长来衡量的——排班时间，减去休息和无法预约的时间。',

        'period' => '时间范围',
        'loading' => '正在更新…',
        'apply' => '应用',
        'cancel' => '取消',
        'clear' => '清除',
        'search' => '搜索员工…',
        'all_locations' => '全部门店',
        'all_roles' => '全部角色',
        'all_statuses' => '全部状态',
        'filters_active' => '筛选',

        'average' => '平均使用率',
        'average_for' => '团队平均',
        'target' => '目标：:target%',
        'target_met' => '已达成目标',
        'above_target' => '高于目标 :count%',
        'below_target' => '低于目标 :count%',
        'on_target' => '达到目标',

        'booked_line' => '已预约 :hours 小时',
        'available_line' => '共 :hours 小时可预约',
        'unused_line' => ':hours 小时未使用',
        'scheduled_count' => '[1,*] 已排班 :count 人',
        'bookings_count' => '{0} 没有预约|[1,*] :count 个预约',
        'used_of_short' => '已预约 :used／:available 小时',

        'summary' => [
            'scheduled' => '已排班员工',
            'bookable' => '可预约工时',
            'booked' => '已预约工时',
            'unused' => '未使用工时',
            'on_target' => '达到或超过目标',
            'under' => '使用率偏低',
        ],

        'statuses' => [
            'high' => '使用率高',
            'on_target' => '已达成目标',
            'near_target' => '接近目标',
            'low' => '使用率低',
            'very_low' => '非常低',
            'unscheduled' => '未排班',
        ],

        'timeline' => [
            'title' => '团队的一天',
            'legend' => [
                'booked' => '已预约',
                'available' => '可预约',
                'break' => '休息',
                'blocked' => '不可预约',
                'off' => '工作时间之外',
            ],
        ],

        'detail' => [
            'utilization' => '使用率',
            'target' => '目标',
            'bookable' => '可预约',
            'booked' => '已预约',
            'unused' => '未使用',
            'bookings' => '预约',
            'revenue' => '营收',
            'average_value' => '客单价',
            'vs_team' => '对比团队平均',
            'vs_target' => '对比目标',
            'day' => '这一天',
            'daily' => '逐日',
            'services' => '他们做了什么',
            'gaps' => '可用产能',
            'gaps_hint' => '长到足以卖出去的空档，以及能塞进去的内容。',
            'gaps_none' => '这一天没有可以卖出去的空档。',
            'no_bookings' => '这段时间内没有预约。',
            'minutes' => ':count 分钟',
            'close' => '关闭',
            'open_staff' => '打开员工记录',
        ],

        'comparison' => '团队对比',

        'table' => [
            'staff' => '员工',
            'location' => '门店',
            'scheduled' => '已排班',
            'bookable' => '可预约',
            'booked' => '已预约',
            'idle' => '未使用',
            'bookings' => '预约',
            'utilization' => '使用率',
            'target' => '目标',
            'variance' => '差异',
            'revenue' => '营收',
            'status' => '状态',
        ],

        'empty' => '暂无员工排班',
        'empty_hint' => '员工使用率需要先发布工作时间才能计算。',
        'empty_cta' => '查看员工排班',
        'no_matches' => '没有符合这些筛选条件的人。',
    ],
];
