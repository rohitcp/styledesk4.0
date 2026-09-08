<?php

declare(strict_types=1);

return [

    /* 引导流程顶部的页头，位于向导本身之上。 */
    'chrome' => [
        'set_up' => '设置你的门店',
    ],

    'business' => [
        'types' => [
            'none' => '还没有设置任何门店类型。请让管理员先添加，然后再继续。',
        ],

        'slug' => [
            'hint' => '根据你的门店名称自动填写——如果想要更短的，可以自行修改。',
            'checking' => '正在检查…',
            'available' => '可以使用',
            'taken' => '已被占用——换一个试试。',
            'reserved' => '这个地址是保留的，请另选一个。',
            'invalid' => '只能使用小写字母、数字和连字符。',
            'regenerate' => '根据门店名称重新生成',
        ],
    ],

];
