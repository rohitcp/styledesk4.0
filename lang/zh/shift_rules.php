<?php

declare(strict_types=1);

/*
| 应用设置 → 员工 → 班次规则。
|
| 班次规则是一套可复用的工作规律；员工排班则是把某条规则应用到某个人身上的结果。
| 文案刻意围绕这个区分来写，因为这正是读者最容易搞混的地方。
*/

return [

    'title' => '班次规则',
    'intro' => '可复用的工作规律。规则是模板——把它指派给某个人、以及改动他们实际上班的某一天，是在"员工 → 员工排班"里做的。',
    'add' => '添加班次规则',
    'add_title' => '添加一条班次规则',
    'edit_title' => '编辑班次规则',
    'saving' => '正在保存…',
    'save' => '保存规则',
    'save_and_add_another' => '保存并继续添加',

    'created' => ':name 已保存。',
    'updated' => ':name 已更新。',
    'deleted' => '班次规则已删除。',
    'duplicated' => '已复制 :name。给副本起个自己的名字再保存。',
    'copy_of' => ':name（副本）',
    'made_active' => ':name 已启用。',
    'made_inactive' => ':name 不再提供给新的排班使用。',

    'sections' => [
        'basics' => '基本信息',
        'days' => '工作日与工作时间',
        'break' => '休息',
        'limits' => '工时规则',
        'flexibility' => '灵活性',
        'dates' => '生效日期',
        'split' => '分段班设置',
    ],

    'fields' => [
        'name' => '规则名称',
        'name_placeholder' => '标准全职',
        'description' => '描述',
        'description_placeholder' => '什么时候、为什么该用这条规则。',
        'location_scope' => '适用范围',
        'locations' => '门店',
        'locations_placeholder' => '搜索或选择门店',
        'status' => '状态',

        'break_type' => '休息',
        'break_minutes' => '休息时长',
        'break_starts_at' => '休息开始',
        'break_ends_at' => '休息结束',

        'max_hours_per_day' => '每天最长工时',
        'max_hours_per_week' => '每周最长工时',
        'min_hours_per_shift' => '每个班次最短工时',
        'max_hours_per_shift' => '每个班次最长工时',
        'min_rest_hours' => '两个班次之间的最短休息',
        'min_rest_hours_hint' => '避免出现晚上 11 点下班、早上 6 点又上班的排班。',
        'max_consecutive_days' => '最多连续工作天数',

        'allow_overtime' => '允许加班',
        'overtime_after_hours' => '超过多少小时算加班',
        'max_overtime_hours' => '最长加班时间',
        'allow_adjustment' => '允许调整排班',
        'allow_adjustment_hint' => '管理者可以挪动某一个生成出来的班次，而不改动这条规则。',
        'allow_split_shift' => '允许员工上分段班',
        'allow_split_shift_hint' => '允许一名员工在同一天上多个互相分开的班次时段。',
        'allow_same_employee_multiple_periods' => '允许同一名员工上多个时段',
        'allow_same_employee_multiple_periods_hint' => '同一个人一天可以上多个时段，前提是这些时段不重叠。',
        'max_periods_per_employee_per_day' => '每名员工每天最多几个班次时段',
        'min_gap_minutes' => '分段班之间的最短间隔',
        'min_gap_hint' => '同一个人上的两个时段之间必须留出的非工作时间。',
        'minutes_unit' => '分钟',

        'effective_from' => '生效起始',
        'effective_until' => '生效截止',
        'effective_hint' => '两者都留空，表示这条规则始终有效。',

        'hours_unit' => '小时',
        'hours_per_week' => '小时／周',
        'days_unit' => '天',
    ],

    'columns' => [
        'day' => '星期',
        'business_hours' => '营业时间',
        'name' => '规则',
        'location' => '门店',
        'days' => '工作日',
        'hours' => '默认工时',
        'weekly' => '每周工时',
        'overtime' => '加班',
        'status' => '状态',
        'assigned' => '员工',
        'updated' => '最近更新',
    ],

    'statuses' => [
        'active' => '启用',
        'inactive' => '停用',
    ],

    'location_scopes' => [
        'all' => '全部门店',
        'specific' => '指定门店',
    ],

    'break_types' => [
        'none' => '不休息',
        'fixed' => '固定时段休息',
        'duration' => '只定时长',
    ],

    'break_custom' => '自定义',
    'minutes' => ':count 分钟',
    'hours_vary' => '每天不同',
    'no_working_days' => '没有工作日',
    'all_locations' => '全部门店',
    'overtime_allowed' => '允许',
    'overtime_not_allowed' => '不允许',
    'not_set' => '未设置',

    'assigned_staff' => '已指派的员工',
    'assigned_count' => '{0} 还没有员工使用这条规则|[1,*] :count 名员工',
    'assigned_where' => '员工是在"添加员工"或"编辑员工"表单里被放到某条规则上的。生成他们实际的、有日期的排班，是在"员工 → 员工排班"里。',

    'duplicate' => '复制',
    'activate' => '启用',
    'deactivate' => '停用',
    'deactivate_confirm' => '停用 :name？它在已经使用它的排班上仍然可读，只是不再提供给新的排班。',
    'delete_confirm' => '删除 :name？这条规则将被永久移除。',
    'delete_blocked' => ':name 正被 :count 名员工使用，因此无法删除。请改为停用——使用它的那些排班需要它保持可读。',

    'validation' => [
        'period_name_required' => '请给每个班次时段起个名字。',
        'period_times_required' => '请给每个班次时段填写开始和结束时间。',
        'period_ends_after_starts' => '班次时段的结束必须晚于开始。',
        'period_outside_business_hours' => '班次时段必须落在商户的营业时间之内。',
        'periods_required' => '请至少添加一个班次时段，或者把分段班关掉。',
        'period_break_too_long' => '休息时间比这个时段还长。',
        'name_required' => '请给这条规则起个名字。',
        'name_taken' => '已经存在同名的规则。',
        'days_required' => '请至少选择一个工作日。',
        'ends_after_starts' => '结束时间必须晚于开始时间。',
        'periods_overlap' => ':day 的工作时段互相重叠。',
        'split_not_allowed' => '这条规则不允许分段班，所以 :day 只能有一个时段。',
        'break_too_long' => '休息时间比最短的那个工作日还长。',
        'break_minutes_required' => '请选择休息多长时间。',
        'break_times_required' => '请为固定休息填写开始和结束时间。',
        'weekly_hours_positive' => '每周最长工时必须大于零。',
        'min_shift_over_max' => '每个班次的最短工时不能大于最长工时。',
        'until_before_from' => '生效截止日期不能早于生效起始日期。',
        'locations_required' => '请至少选择一家门店，或把规则设为适用于所有门店。',
    ],

    'results' => [
        'zero' => '未找到班次规则',
        'one' => '找到 1 条班次规则',
        'many' => '找到 :count 条班次规则',
        'empty' => '没有符合搜索或筛选条件的班次规则。',
        'clear' => '清除筛选',
    ],
    'showing' => '显示第 :from–:to 条班次规则，共 :total 条',
    'none_yet' => '还没有班次规则',
    'none_yet_hint' => '班次规则是一套你写一次、就能应用到任意多人身上的工作规律——"标准全职""周末班""兼职早班"。',

    'weekdays_short' => [
        0 => '日',
        1 => '一',
        2 => '二',
        3 => '三',
        4 => '四',
        5 => '五',
        6 => '六',
    ],

    'filters' => [
        'all_statuses' => '全部状态',
        'all_locations' => '全部门店',
    ],

    /* 这个周编辑器和门店营业时间页面用的是同一个组件，所以只覆盖措辞不同的地方：
       规律讲的是工作日和休息日，而分店讲的是营业和休息。 */
    'hours_editor' => [
        'open' => '上班',
        'closed' => '休息',
        'closed_all_day' => '不上班',
        'add_period' => '再加一个时段',
        'opening_time' => ':day 的开始时间',
        'closing_time' => ':day 的结束时间',
    ],

    'hours_are_global' => '商户的营业时间在排班和预约中都会用到。在这里改动它，等于在所有地方都改了。',
    'edit_business_hours' => '编辑商户营业时间',
    'no_location_yet' => '写规则之前请先添加一家门店——一周的工作安排是属于某家分店的。',
    'closed' => '休息',

    'sections_split' => '分段班设置',
    'split_intro' => '把营业日划分成有名字的时段，用来安排人手。每个时段都必须落在商户的营业时间之内。',

    'periods' => [
        'name' => '班次名称',
        'name_placeholder' => '早班',
        'starts_at' => '开始时间',
        'ends_at' => '结束时间',
        'break' => '休息',
        'no_break' => '不休息',
        'status' => '状态',
        'active' => '启用',
        'inactive' => '停用',
        'add' => '+ 添加班次时段',
        'remove' => '移除',
        'minutes' => ':count 分钟',
        'empty' => '还没有班次时段。添加第一个，把营业日划分开。',
    ],

    'enable' => '启用班次规则',
    'enable_hint' => '为这家商户启用可复用的员工排班规则。',
    'feature_on' => '班次规则已开启。',
    'feature_off' => '班次规则已关闭。没有删除任何内容。',
    'feature_off_title' => '班次规则已关闭',
    'feature_off_kept' => '{0} 打开它，就可以写一套能应用到任意多人身上的工作规律。|[1,*] 你的 :count 条规则都还在——打开班次规则即可看到它们。',
    'rule_count' => '{0} 班次规则|[1,*] :count 条班次规则',
    'back_to_list' => '返回规则列表',
    'delete_title' => '删除班次规则？',
];
