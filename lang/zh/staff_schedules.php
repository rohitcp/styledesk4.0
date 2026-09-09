<?php

declare(strict_types=1);

/*
| 团队排班总览：一人一行，一月一列。
|
| 措辞把三种状态严格分开，因为整个页面存在的意义就是让人一眼分辨它们：已发布是
| 已经把邮件发给这个人的月份，草稿是已经排好但还没发出的月份，未排班是还没有人
| 想过的月份。
*/

return [

    'title' => '员工排班',
    'intro' => '哪些月份已经安排好、哪些还是草稿，以及谁还什么都没排。',
    'summary' => '{0} 没有员工|[1,*] :count 名员工',

    'assign' => '安排排班',
    'add_shift' => '添加班次',

    'search_label' => '搜索员工',
    'search_placeholder' => '按姓名、邮箱或职位搜索',

    'filters' => [
        'month' => '月份',
        'year' => '年份',
        'status' => '排班状态',
        'coverage' => '排班范围',
        'all_statuses' => '全部状态',
        'all_coverage' => '全部月份',
        'apply' => '应用',
        'reset' => '重置',
    ],

    'statuses' => [
        'scheduled' => '已排班',
        'not-scheduled' => '未排班',
        'draft' => '草稿',
        'published' => '已发布',
    ],

    'coverage' => [
        'completed' => '已过去的月份',
        'current' => '本月',
        'future' => '未来的月份',
    ],

    /*
    | 一个格子会说什么。"有改动"是按人查看的页面本来就保留的第四种状态：已经发出去
    | 过、之后又改过——在员工看来这是草稿，在他们的收件箱看来则不是。
    */
    'states' => [
        'published' => '已发布',
        'draft' => '草稿',
        'changes' => '有改动待发布',
        'not-scheduled' => '未排班',
    ],

    'columns' => [
        'staff' => '员工',
    ],

    'summary_line' => ':year 年 :month 月',
    'shifts_count' => '[1,*] :count 个班次',
    'scroll_hint' => '左右滚动可以看到每一个月。',
    'this_month' => '本月',
    'cell_hint' => ':name · :month',
    'open_schedule' => '打开 :name 在 :month 的排班',
    'start_schedule' => '为 :name 安排 :month 的排班',

    'menu' => [
        'view' => '查看 :month 的排班',
        'assign' => '安排 :month 的排班',
    ],

    'actions_for' => '对 :name 的操作',
    'showing' => '显示第 :from–:to 名员工，共 :total 名',
    'results' => [
        'zero' => '没有员工',
        'one' => '1 名员工',
        'many' => ':count 名员工',
        'clear' => '清除筛选',
    ],
    'empty' => '没有符合这些筛选条件的员工。',
    'empty_hint' => '清除筛选条件即可看到整个团队。',
    'no_months' => '没有符合这个范围筛选的月份。',

    'start' => [
        'title' => '安排排班',
        'intro' => '选择这份排班是给谁的、覆盖哪个月。一个月是整体安排的。',
        'staff' => '员工',
        'choose_staff' => '搜索或选择一名员工',
        'continue' => '继续排班',
    ],

    'month' => [
        'date' => '日期',
        'day' => '星期',
        'shift' => '班次',
        'start' => '开始',
        'end' => '结束',
        'break' => '休息',
        'hours' => '总工时',
        'status' => '状态',
        'split' => '第 :index 个班次',
        'minutes' => ':count 分钟',
        'edit' => '编辑／重新安排排班',
        'loading' => '正在加载…',
        'failed' => '这份排班加载失败。',
    ],

    'legend' => [
        'title' => '图例',
        'published' => '这个月的排班已经用邮件发给了每个人。',
        'draft' => '已经排好，但还没有告诉任何人。',
        'not_scheduled' => '什么都没排——需要处理。',
    ],
];
