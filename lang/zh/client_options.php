<?php

declare(strict_types=1);

/*
| 客户模块的目录标签。
|
| 键名就是存入数据库的值，因此 config/clients.php 始终是下拉框、校验规则和客户
| 模块共同读取的那一份列表，而各语言只决定这些选项叫什么。
|
| 这里的每个英文原值都是 config/clients.php 早已使用的标签。
*/

return [
    'fields' => [
        'first_name' => '名字',
        'last_name' => '姓氏',
        'mobile' => '手机号码',
        'email' => '电子邮箱',
        'date_of_birth' => '出生日期',
        'gender' => '性别',
        'address' => '地址',
        'city' => '城市',
        'state' => '省／州',
        'postal_code' => '邮政编码',
        'country' => '国家／地区',
        'preferred_location' => '偏好门店',
        'preferred_staff' => '偏好员工',
        'avatar' => '头像',
        'notes' => '备注',
    ],

    'name_formats' => [
        'first_last' => '先名字，后姓氏',
        'last_first' => '先姓氏，后名字',
        'first_initial' => '名字加姓氏首字母',
        'preferred_last' => '先常用名，后姓氏',
    ],

    'phone_types' => [
        'mobile' => '手机',
        'home' => '住宅',
        'work' => '工作',
        'other' => '其他',
    ],

    'email_types' => [
        'personal' => '个人',
        'work' => '工作',
        'other' => '其他',
    ],

    'statuses' => [
        'active' => '正常',
        'inactive' => '停用',
        'archived' => '已归档',
    ],

    'default_statuses' => [
        'active' => '正常',
        'inactive' => '停用',
    ],

    'communication_methods' => [
        'email' => '邮件',
        'sms' => '短信',
        'phone' => '电话',
        'none' => '没有偏好',
    ],

    'marketing_defaults' => [
        'ask' => '询问客户',
        'in' => '已同意',
        'out' => '已拒绝',
    ],

    'duplicate_rules' => [
        'email' => '相同的电子邮箱',
        'mobile' => '相同的手机号码',
        'name_mobile' => '姓名和手机号码都相同',
    ],

    'search_fields' => [
        'first_name' => '名字',
        'last_name' => '姓氏',
        'mobile' => '手机号码',
        'email' => '电子邮箱',
        'client_id' => '客户编号',
    ],

    'creation_sources' => [
        'client_list' => '客户列表',
        'booking' => '预约页面',
        'calendar' => '日历',
        'walk_in' => '到店预约',
        'pos' => '收银台',
    ],

    'booking_panels' => [
        'preferred_staff' => '偏好员工',
        'preferred_location' => '偏好门店',
        'preferences' => '客户偏好',
        'notes' => '重要备注',
        'last_booking' => '上次预约',
        'recent_visits' => '最近到店',
        'recent_staff' => '最近服务的员工',
        'rating' => '客户平均评分',
        'book_same_again' => '再约一次相同内容',
    ],

    'history_panels' => [
        'upcoming' => '即将到来的预约',
        'previous' => '过往预约',
        'cancelled' => '已取消的预约',
        'no_shows' => '爽约记录',
        'services' => '曾预约的服务',
        'staff' => '曾预约的员工',
        'locations' => '到访过的门店',
        'notes' => '客户备注',
        'preferences' => '偏好',
        'activity' => '预约动态',
    ],

    'tag_colors' => [
        'slate' => '石板灰',
        'violet' => '紫罗兰',
        'blue' => '蓝色',
        'teal' => '青色',
        'green' => '绿色',
        'amber' => '琥珀色',
        'rose' => '玫红',
        'plum' => '梅子紫',
    ],
];
