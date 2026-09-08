<?php

declare(strict_types=1);

/*
| 给某一位员工安排工作排班。
|
| 措辞把三件事严格分开，因为它们很容易混淆，而这个区分正是靠文案立起来的：商户的
| 营业时间说的是门店什么时候开门，班次规则是某个人所遵循的规律，而下面的排班是这个
| 规律生成出来的、有具体日期的现实。
*/

return [

    'year' => '年份',
    'monthly_summary' => '月度汇总',
    'month_hours' => '工时',
    'month_shifts' => '班次',
    'month_days' => '工作日',
    'bookings_pending' => '预约数量会随预约模块一起到来。',

    'working_schedule' => '工作排班',
    'assign' => '安排排班',
    'assign_title' => '安排排班',
    'assign_intro' => '为所选的员工和日期范围安排工作排班。',
    'assign_for' => '员工',
    'period' => '排班周期',
    'assigning' => '正在安排…',

    'add_shift' => '添加班次',
    'add_for_day' => '添加排班',
    'delete_day' => '删除排班',
    'delete_day_title' => '删除排班？',
    'delete_day_confirm' => '这会移除 :name 在 :date 的工作排班。',
    'day_deleted' => ':date 的排班已删除。',
    'mark_on_leave' => '标记为休假——即将推出',
    'showing' => '显示第 :from–:to 天，共 :total 天',
    'actions_for' => '对 :name 的操作',

    'filters' => [
        'period' => '周期',
        'month' => '月份',
        'year' => '年份',
        'apply' => '应用',
        'edit_period' => '更换月份',
        'reset' => '重置',
    ],

    'periods' => [
        'month' => '一个月',
        '3-months' => '3 个月',
        '6-months' => '6 个月',
        '9-months' => '9 个月',
        'year' => '一年',
    ],

    'columns' => [
        'date' => '日期',
        'day' => '星期',
        'working' => '出勤状态',
        'time' => '时间',
        'status' => '状态',
        'published_on' => '发布时间',
        'published_by' => '发布人',
        'hours' => '总工时',
        'bookings' => '预约',
    ],

    'empty' => '未找到排班',
    'empty_hint' => '所选周期内没有员工排班记录。',

    'duration_intro' => '整个月是一次性排好的。排班打开之后，你可以改动任何一天。',
    'continue_label' => '继续',
    'back' => '返回',
    'close' => '关闭',
    'save' => '保存',
    'save_and_publish' => '保存并发布',
    'update_and_publish' => '更新并发布',
    'rule_change_title' => '按这条规则重建各天？',
    'rule_change_confirm' => '下面的各天会按你选择的规则重新填写，你在这里做过的改动会丢失。',
    'rule_change_label' => '重建各天',
    'leave_title' => '不保存就离开？',
    'leave_confirm' => '这份排班有尚未保存的改动。现在离开会放弃它们。',
    'leave_confirm_label' => '放弃更改',
    'needs_javascript' => '编排班表需要 JavaScript，而这个浏览器已将其关闭。',

    'summary' => '{0} 没有安排任何内容|[1,*] 已排 :hours 小时 · :count 个工作日',
    'hours_short' => ':count 小时',

    'working' => '上班',
    'off' => '休息',
    'not_working' => '不上班',
    'week_number' => '第 :number 周',
    'add_period' => '+ 添加工作时段',
    'remove_period' => '移除这个时段',
    'starts_at' => '开始',
    'ends_at' => '结束',
    'break' => '休息',
    'no_break' => '不休息',

    'assigned' => '[1,*] 已安排 :count 个班次。',
    'nothing_to_assign' => '没有选择任何工作日，所以什么都没有安排。',
    'replaced_note' => '安排排班会替换这个范围内已有的班次。',

    'shift_rule' => '班次规则',
    'no_shift_rule' => '没有班次规则',
    'change_rule' => '更换',
    'prefill_hint' => '选择一条规则，会按商户的营业时间和这条规则自己的班次时段来填写下面的各天。在这里改动某一天，只会改动这份排班，绝不会改动规则本身。',

    'refused' => '[1,*] 排班未保存：有 :count 天违反了班次规则。',
    'refused_title' => '这份排班没有被保存。',

    'publish_statuses' => [
        'draft' => '草稿',
        'published' => '已发布',
    ],

    /*
    | 发布。
    |
    | 安排排班和告诉某个人是两件事，而这个区分正是靠文案立起来的：草稿是管理者还在
    | 想，已发布则是这名员工实实在在的一周。
    */
    'draft_badge' => '排班草稿',
    'published_badge' => '已发布',
    'published_notice_title' => '这份排班已经发布过了。',
    'published_notice' => '你在这里做的任何改动都会更新 :name 已发布的排班。再次发布时，会用邮件通知他们排班有变。存为草稿则不会告诉他们任何事。',
    'locked' => '已发布——员工已经知道这一天的安排。',
    'published_on' => '发布于 :date',
    'published_by' => '由 :name',
    'changes_badge' => '有改动未发布',
    'changes_hint' => '这份排班于 :date 发布，之后又被改动过。:name 还不知道这些改动。',
    'draft_hint' => '目前还什么都没有发出。只有在你发布时，:name 才会收到邮件。',
    'published_hint' => '这份排班已经用邮件发给了 :name。',

    'save_draft' => '保存草稿',
    'publish' => '发布排班',
    'publish_changes' => '发布改动',
    'publishing' => '正在发布…',

    'draft_saved' => '排班已存为草稿。',
    'published' => '排班已成功发布。已通过邮件通知 :name。',
    'republished' => '排班已更新并发布。已通知 :name。',
    'published_without_email' => '排班已发布。:name 档案里没有电子邮箱，因此没有发送通知。',
    'published_email_failed' => '排班已发布，但发给 :name 的邮件没能送出。失败已记入日志。',
    'nothing_to_save' => '这个周期内没有可保存的排班内容。',
    'nothing_to_publish' => '这个周期内没有可发布的排班内容。',

    'confirm' => [
        'title' => '发布员工排班？',
        'intro' => '发布前请先检查这份排班。一旦发布，会用邮件通知这名员工。',
        'staff_member' => '员工',
        'period' => '排班周期',
        'duration' => '时长',
        'working_days' => '工作日',
        'total_hours' => '排定总工时',
        'weeks' => '[1,*] :count 周',
        'hours' => '[1,*] :count 小时',
        'days' => ':count',
        'days_long' => '[1,*] :count 天',
        'will_notify' => '排班会被发布，并用邮件通知这名员工。',
        'consequence' => '一旦发布，这份排班就成为该员工正式的工作排班，并会用邮件通知他们。',
    ],

    'confirm_draft' => [
        'title' => '把排班存为草稿？',
        'intro' => '排班会被保存，但在发布之前不会发给这名员工。',
    ],

    'day_total' => '[1,*] :count 小时',

    'email' => [
        'subject' => '你的工作排班已发布',
        'subject_updated' => '你的工作排班有更新',
        'headline_updated' => '你的工作排班有更新',
        'intro_updated' => '这份取代了之前发给你的排班。以下是完整内容。',
        'preheader' => '你 :from – :until 的排班。',
        'headline' => '你的工作排班已发布',
        'greeting' => '你好 :name：',
        'intro' => ':business 已经发布了你的工作排班。以下是完整内容。',
        'period' => '排班周期',
        'location' => '门店',
        'working_days' => '工作日',
        'total_hours' => '排定总工时',
        'published_on' => '发布时间',
        'published_by' => '发布人',
        'hours' => '[1,*] :count 小时',
        'daily_schedule' => '每日安排',
        'not_working' => '不上班',
        'day_total' => '[1,*] 合计：:count 小时',
        'cta' => '查看我的排班',
        'questions' => '如果这里有任何看起来不对的地方，请与你在 :business 的主管沟通。',
    ],

    'validation' => [
        'staff_not_active' => ':name 不在职，因此无法安排排班。',
        'ends_after_starts' => '结束时间必须晚于开始时间。',
        'periods_overlap' => '这一天的工作时段互相重叠。',
        'split_not_allowed' => '所指定的班次规则不允许分段班，因此这一天只能有一个时段。',
        'one_period_only' => '所指定的班次规则不允许同一个人一天上超过一个时段。',
        'too_many_periods' => '所指定的班次规则一天最多允许 :count 个时段。',
        'gap_too_short' => '所指定的班次规则要求分段班之间间隔 :hours 小时。',
        'business_closed' => '门店这一天不营业。',
        'outside_business_hours' => '超出了商户的营业时间（:hours）。',
        'over_daily_hours' => '超过了班次规则允许的每天 :count 小时。',
        'over_weekly_hours' => '超过了班次规则允许的每周 :count 小时。',
        'not_enough_rest' => '距上一个班次的休息时间不足班次规则要求的 :count 小时。',
        'too_many_consecutive' => '连续工作日超过 :count 天，班次规则不允许。',
        'already_working' => '这一天已经安排了 :hours 的工作。',
    ],
];
