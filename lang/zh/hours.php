<?php

declare(strict_types=1);

/*
| 营业时间模块：总览和按门店的编辑器。
|
| 每周营业时间的卡片本身与门店模块共用，其文案读自 locations 文件，因此无论从哪里
| 打开，同一个编辑器的措辞都一致。（这里写成文字而不是路径：以星号加斜杠结尾的
| 通配符会把它所在的注释关掉。）
*/

return [
    'title' => '营业时间',
    'intro' => '每家门店的营业时间，以及覆盖它们的节假日、停业和特殊营业时间。时间按各门店自己的时区显示。',
    'timezone_note' => '时间以这家门店自己的时区 :name（:identifier）为准。',

    'edit_hours' => '编辑营业时间',
    'save' => '保存营业时间',
    'saved' => '营业时间已保存。',
    'saved_from' => '营业时间已保存，自 :date 起生效。',
    'also_applied' => '[1,*] 同时应用到了另外 :count 家门店。',
    'schedule_discarded' => '已放弃即将生效的营业时间。',
    'correct_fields' => '请修正高亮显示的字段后重试。',

    'no_locations' => '还没有门店。',
    'no_locations_hint' => '营业时间属于某家门店，所以请先添加一家。',
    'add_location' => '添加一家门店',

    'upcoming' => [
        'title' => '即将到来',
        'hint' => '未来 12 个月内的节假日、停业和特殊营业时间。',
        'in_progress' => '进行中',
        'summary' => '[1,*] :count 项即将到来的例外安排',
        'and_more' => '还有 :count 项',
    ],

    'future' => [
        'starts' => '新的营业时间自 :date 起生效。',
        'review' => '去查看',
        'editing' => '你正在编辑 :date 起生效的营业时间。今天的营业时间不受影响。',
        'edit_today' => '改为编辑今天的营业时间',
        'pending' => '另一套营业时间将于 :date 生效。这里的改动在那之前有效。',
        'edit_those' => '改为编辑那一套',
        'discard' => '放弃',
        'discard_confirm' => '放弃这套即将生效的营业时间？当前的营业时间会继续沿用。',
    ],

    'effective' => [
        'title' => '这套营业时间何时开始',
        'hint' => '留空表示改动当前生效的营业时间。选一个日期则是提前安排变更——在那之前仍沿用现在的时间。',
        'label' => '生效日期',
    ],

    'apply' => [
        'title' => '应用到其他门店',
        'hint' => '把这一周复制到你勾选的分店。之后每家各自保留一份，所以你仍然可以只改其中一家而不动其余。',
        'warning' => '这会替换被勾选门店在同一时期的营业时间。',
    ],

    'exceptions' => [
        'title' => '节假日、停业与特殊营业时间',
        'hint' => '会覆盖上面每周营业时间的日期。',
        'add' => '添加日期',
        'empty' => '还没有任何安排。添加一个法定假日、一次停业，或一个营业时间不同的日子。',
        'add_title' => '添加一个日期',
        'edit_title' => '编辑这个日期',
        'save' => '保存日期',
        'added' => '已加入日历。',
        'updated' => '日历条目已更新。',
        'removed' => '已从日历中移除。',
        'delete_confirm' => '把“:name”从日历中移除？',

        'type' => '这是什么',
        'name' => '名称',
        'name_placeholder' => '圣诞节',
        'from' => '从',
        'to' => '至',
        'to_hint' => '只有一天的话，留空即可。',
        'closed_all_day' => '全天休息',
        'closed_all_day_hint' => '关掉它，就改为按不同的时间营业。',
        'opens' => '开始营业',
        'closes' => '结束营业',
        'notes' => '内部备注',
        'notes_placeholder' => '只有你的团队看得到。',
    ],

    'validation' => [
        'name_required' => '给它起个名字，让团队知道这是什么。',
        'date_required' => '请选择一个日期。',
        'end_before_start' => '结束日期不能早于开始日期。',
        'opens_required' => '请填写营业时间，或把这一天标为休息。',
        'closes_required' => '请填写打烊时间，或把这一天标为休息。',
        'closes_after_opens' => '打烊时间必须晚于营业时间。',
        'effective_after' => '未来的营业时间必须从更晚的日期开始。留空则表示改动今天的营业时间。',
        'schedule_not_future' => '只有尚未开始的安排才能放弃。',
        'clash' => '“:name”已经覆盖了 :dates。请改为编辑那一条，或换一个日期。',
    ],

    /*
     * 例外类型。
     *
     * 键名就是存入 location_closures 的值，因此下拉框和校验规则始终读取同一份列表。
     */
    'types' => [
        'public_holiday' => '法定假日',
        'closure' => '门店停业',
        'special_hours' => '特殊营业时间',
        'training' => '员工培训日',
        'maintenance' => '维护停业',
        'private_event' => '私人活动',
        'emergency' => '紧急停业',
    ],
];
