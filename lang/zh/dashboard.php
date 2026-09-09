<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| 仪表板
|--------------------------------------------------------------------------
|
| 谁登录，仪表板回答的问题就不同，因此文案是为阅读的人而写，而不是为底层数据
| 而写。前台想知道谁已经到店；老板想知道这个月经营得怎么样。
|
*/

return [

    'title' => '仪表板',
    'greeting' => [
        'morning' => '早上好',
        'afternoon' => '下午好',
        'evening' => '晚上好',
    ],

    'location' => '门店',
    'all_locations' => '全部门店',

    'performance' => [
        'title' => '经营概况',
        'today' => '今日收入',
        'month' => '本月收入',
        'change' => '较上月同期',
        'outstanding' => '未收款',
        'bookings' => '本月预约数',
        'average' => '平均客单价',
        /* 用文字说明而不是显示为零：第一个月没有可比较的对象，
           “0%”会被读成生意停滞。 */
        'no_comparison' => '暂无上月数据',
    ],

    'bookings_today' => [
        'title' => '今日预约',
        'total' => '总计',
        'pending_checkin' => '待到店',
        'checked_in' => '已到店',
        'completed' => '已完成',
        'cancelled' => '已取消',
        'no_show' => '未到店',
    ],

    'checkin' => [
        'title' => '到店登记',
        'none' => '当前没有人等待登记。',
        'none_hint' => '随着时间推移，预约会显示在这里。',
        'view_all' => '打开队列',
        'action' => '登记到店',
    ],

    'arriving' => [
        'title' => '即将到店',
        'none' => '接下来一小时没有预约。',
    ],

    'waiting' => [
        'title' => '等待中',
        'none' => '没有人在等待。',
        'for' => '已等待 :count 分钟',
        'since' => ':time 登记到店',
        'too_long' => '等待时间较长',
    ],

    'staff_today' => [
        'title' => '今日上班',
        'none' => '今天排班表上没有人。',
        'shift' => '班次',
        'now' => '服务中',
        'next' => '下一位',
        'remaining' => '还剩 :count 位',
        'free' => '空闲',
    ],

    'schedule_issues' => [
        'title' => '排班问题',
        'none' => '没有需要你处理的运营问题。',
        'working_without_a_shift' => '未排班却在工作',
        'bookings_without_staff' => '尚未指派员工的预约',
    ],

    'clients' => [
        'title' => '客户',
        'new_today' => '今日新增',
        'booked_today' => '今日到店',
        'returning_today' => '回头客',
        'active' => '活跃客户',
    ],

    'services' => [
        'title' => '本月预约最多',
        'none' => '本月还没有任何预约。',
        'bookings' => '已预约 :count 次',
    ],

    'payments' => [
        'title' => '收款',
        'collected' => '今日收入',
        'outstanding' => '未收款',
        'partial' => '部分付款',
        'due_today' => '今日到店且未付款',
        'none' => '今天没有欠款。',
    ],

    'alerts' => [
        'title' => '需要关注',
        'none' => '没有需要你处理的运营问题。',
        'late' => ':count 位待到店的客户已超过预约时间',
        'waiting_too_long' => ':count 位客户已等待较长时间',
        'unpaid' => '今日到店的 :count 个预约尚未付款',
        'unstaffed' => '今天有 :count 个预约尚未指派员工',
    ],

    'my_next_client' => [
        'title' => '下一位客户',
        'none' => '今天你没有其他预约了。',
        'none_hint' => '今天稍后新增的预约会显示在这里。',
        'here' => '已到店',
        'view_client' => '查看客户',
        'view_booking' => '查看预约',
    ],

    'my_day' => [
        'title' => '我的一天',
        'none' => '今天没有为你安排预约。',
    ],

    'my_schedule' => [
        'title' => '我的班次',
        'none' => '今天你不在排班表上。',
        'from' => '开始',
        'to' => '结束',
        'break' => '休息',
        'remaining' => '尚未开始',
    ],

    'my_performance' => [
        'title' => '我的今日进度',
        'clients' => '客户',
        'completed' => '已完成',
        'average' => '平均服务时长',
        'tips' => '小费',
        'minutes' => ':count 分钟',
    ],

    'quick_actions' => [
        'title' => '快捷操作',
        'booking' => '创建预约',
        'client' => '添加客户',
        'checkin' => '为客户登记到店',
        'staff' => '添加员工',
        'service' => '添加服务',
        'schedule' => '管理排班',
        'calendar' => '查看日历',
    ],

    /* 设置清单，完成或忽略最后一项前一直显示。 */
    'getting_started' => [
        'title' => '快速上手',
        'intro' => '还有几项设置没有完成。你随时可以回来继续。',
        'dismiss' => '不再显示',

        /*
        | 清单本身。
        |
        | 放在这里而不是 DashboardController 里——它们原本是九条写死的英文：
        | 用 PHP 拼出来的列表同样是屏幕上的文案，结果中文读者看到的是整份英文清单，
        | 而包着它的卡片却是翻译好的。
        */
        'items' => [
            'service' => '添加你的第一项服务',
            'team' => '添加团队成员',
            'schedules' => '配置员工排班',
            'client' => '添加你的第一位客户',
            'online_booking' => '设置在线预约',
            'payments' => '配置收款',
            'reminders' => '配置预约提醒',
            'branding' => '添加你的标志和品牌样式',
            'appointment' => '创建你的第一个预约',
        ],
    ],
];
