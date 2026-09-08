<?php

declare(strict_types=1);

/*
| 系统角色。
|
| StyleDesk 自己的用语，按角色的键名索引而不是按存下来的名称——这样门店自己创建并
| 命名的角色会保留自己的说法，就像服务名称或客户备注一样。
|
| 每个英文原值都是 config/role_defaults.php 早已使用的名称。
*/

return [
    'owner' => [
        'name' => '所有者',
        'description' => '对门店、员工、设置、账务和运营数据拥有完全权限。',
    ],
    'administrator' => [
        'name' => '管理员',
        'description' => '拥有完整的运营和管理权限，仅所有者专属的受保护操作除外。',
    ],
    'manager' => [
        'name' => '店长',
        'description' => '负责其门店的日常运营、员工、服务、客户和报表。',
    ],
    'front-desk' => [
        'name' => '前台',
        'description' => '预约、客户、下单、签到与结账，以及前台相关事务。',
    ],
    'service-provider' => [
        'name' => '服务人员',
        'description' => '自己的日程、预约、被分配的客户和服务。',
    ],

    /*
    | 角色名称下方的数字。
    |
    | 由语言文件给出完整短语的复数形式，而不是交给 Str::plural()——它只懂英语，
    | 会写出"2 miembro del personals"这样的东西。
    */
    'permissions_summary' => '共 :total 项权限，已授予 :granted 项',
    'staff_summary' => '{0} 没有员工|[1,*] :count 名员工',
];
