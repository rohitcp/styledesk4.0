<?php

declare(strict_types=1);

/*
| 员工模块的选项列表。
|
| 键名就是存入数据库的值，因此下拉框和校验规则始终读取 config/staff.php 里的
| 同一份列表，而各语言只决定这些选项叫什么。
|
| 人称代词是值得说明的例外："she/her" 不是逐词翻译的短语，而是一个人如何称呼
| 自己，所以每种语言各自给出自己的一套，而不是照搬英文语法。
*/

return [
    'employment_types' => [
        'full-time' => '全职员工',
        'part-time' => '兼职员工',
        'contractor' => '承包人员',
        'independent' => '独立服务人员',
        'commission' => '提成制',
        'booth-renter' => '租位／租椅人员',
        'freelancer' => '自由职业者',
        'temporary' => '临时员工',
        'apprentice' => '学徒／培训生',
        'intern' => '实习生',
        'volunteer' => '志愿者',
        'other' => '其他',
    ],

    'provider_types' => [
        'service-provider' => '服务人员',
        'non-provider' => '非服务人员',
        'manager-provider' => '管理者兼服务人员',
        'front-desk' => '前台接待',
        'administrative' => '行政人员',
        'support' => '后勤人员',
    ],

    'specialities' => [
        'hair-stylist' => '发型师',
        'barber' => '理发师',
        'colourist' => '染发师',
        'nail-technician' => '美甲师',
        'massage-therapist' => '按摩师',
        'esthetician' => '美容师',
        'makeup-artist' => '化妆师',
        'lash-technician' => '美睫师',
        'brow-specialist' => '美眉师',
        'therapist' => '理疗师',
        'consultant' => '顾问',
    ],

    'pronouns' => [
        'she/her' => '她',
        'he/him' => '他',
        'they/them' => 'TA',
        'she/they' => '她／TA',
        'he/they' => '他／TA',
        'prefer-not-to-say' => '不愿透露',
    ],

    'phone_types' => [
        'mobile' => '手机',
        'work' => '工作',
        'home' => '住宅',
        'other' => '其他',
    ],

    'statuses' => [
        'active' => '在职',
        'inactive' => '停用',
        'on-leave' => '休假中',
        'pending-invite' => '待接受邀请',
        'invite-queued' => '邀请待发送',
        'invite-failed' => '邀请发送失败',
        'invite-expired' => '邀请已过期',
        'suspended' => '已停职',
        'archived' => '已归档',
    ],

    'sorts' => [
        'name' => '姓名',
        'recent' => '最近添加',
        'role' => '角色',
        'location' => '门店',
        'status' => '状态',
    ],
];
