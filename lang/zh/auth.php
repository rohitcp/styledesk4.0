<?php

declare(strict_types=1);

/*
| 登录表单拒绝登录时所显示的内容。
|
| 覆盖框架自带的文件，使措辞符合 StyleDesk 的风格。'failed' 对密码错误和
| 账户不存在刻意给出相同的回答：区分两者等于告诉陌生人哪些邮箱在此注册过。
*/

return [
    'failed' => '邮箱或密码不正确，请重试。',

    /* 登录过程本身出错，而非被拒绝——参见
       App\Http\Middleware\ReportSignInFailures。刻意不说明出了什么问题：
       读者无法据此采取行动，而日志可以。 */
    'unavailable' => '我们暂时无法为你登录，请重试。',

    'unverified' => '你的邮箱地址尚未验证。请先验证邮箱以继续。',
    'password' => '密码不正确。',
    'throttle' => '登录尝试次数过多，请在 :seconds 秒后重试。',

    /* 说得明白些。账户被停用的人应当从屏幕上得知，
       而不是发现密码莫名其妙失效了。 */
    'business_disabled' => '你的 StyleDesk 账户目前已停用。请联系客服寻求帮助。',
];
