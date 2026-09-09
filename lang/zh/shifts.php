<?php

declare(strict_types=1);

/*
| 班次页面：某个具体日期上的工作时间。
|
| 措辞刻意与旁边的员工排班区分开——排班是重复的规律，班次是一个有日期的时间块——
| 因为两者很容易混淆，而这个区分正是靠文案立起来的。
*/

return [

    'title' => '班次',
    'intro' => '某个具体日期上的工作时间。班次会覆盖那一天的重复排班。',
    'add' => '添加班次',
    'add_title' => '添加一个班次',
    'edit_title' => '编辑班次',
    'adding' => '正在添加…',
    'saving' => '正在保存…',
    'save' => '保存班次',
    'save_and_add_another' => '保存并继续添加',

    'created' => '已为 :name 添加班次。',
    'updated' => '班次已更新。',
    'deleted' => '班次已移除。',
    'delete_confirm' => '移除 :name 在 :date 的这个班次？',
    'correct_fields' => '请检查高亮显示的字段后重试。',

    'fields' => [
        'staff' => '员工',
        'staff_placeholder' => '搜索或选择员工',
        'location' => '门店',
        'location_placeholder' => '搜索或选择一家门店',
        'date' => '日期',
        'starts_at' => '开始时间',
        'ends_at' => '结束时间',
        'break' => '休息',
        'break_hint' => '班次内不计薪的时间。',
        'type' => '班次类型',
        'status' => '状态',
        'notes' => '备注',
        'notes_placeholder' => '看排班表的人应该知道的任何事。',
    ],

    'columns' => [
        'staff' => '员工',
        'date' => '日期',
        'hours' => '工时',
        'break' => '休息',
        'location' => '门店',
        'type' => '类型',
        'status' => '状态',
    ],

    'filters' => [
        'all_staff' => '全部员工',
        'all_locations' => '全部门店',
        'all_types' => '全部类型',
        'all_statuses' => '全部状态',
        'from' => '从',
        'until' => '至',
    ],

    'types' => [
        'regular' => '常规',
        'overtime' => '加班',
        'cover' => '顶班',
        'training' => '培训',
        'on-call' => '待命',
        'custom' => '自定义',
    ],

    'statuses' => [
        'scheduled' => '已排定',
        'confirmed' => '已确认',
        'completed' => '已完成',
        'cancelled' => '已取消',
    ],

    'minutes' => ':count 分钟',
    'no_break' => '无',

    'view' => '查看班次',
    'duplicate' => '复制',
    'cancel_shift' => '取消班次',
    'cancel_confirm' => '取消这个班次？它会保留在排班表上并标记为已取消，让所有人都看得到它被撤掉了。',
    'cancelled_toast' => '班次已取消。',

    'validation' => [
        'business_closed' => ':day 门店不营业，所以那天没法给任何人排班。请换一天，或到"应用设置 → 商户 → 营业时间"把那天设为营业。',
        'outside_business_hours' => '这个时间段落在门店的营业时间（:hours）之外。',
        'ends_after_start' => '结束时间必须晚于开始时间。',
        'break_too_long' => '休息时间比整个班次还长。',
        'clash' => ':name 那天已经有一个班次与这段时间重叠。',
        'staff_required' => '请选择这个班次由谁来上。',
        'date_required' => '请选择这个班次在哪一天。',
    ],

    'results' => [
        'zero' => '未找到班次',
        'one' => '找到 1 个班次',
        'many' => '找到 :count 个班次',
        'empty' => '没有符合搜索或筛选条件的班次。',
        'clear' => '清除筛选',
    ],
    'showing' => '显示第 :from–:to 个班次，共 :total 个',
    'none_yet' => '还没有班次',
    'none_yet_hint' => '当有人要上的工时不在他们的重复排班里时，就添加一个班次——一个周六、一次顶班、一晚培训。',
];
