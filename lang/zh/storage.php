<?php

declare(strict_types=1);

/**
 * 存储组件拒绝一个文件时会说的话。
 *
 * 每一句都点明限制而不是失败本身："这张图片超过 5 MB"告诉读者接下来该怎么做，
 * 而"上传失败"只告诉他们出了点问题。
 */
return [
    'upload_failed' => '这个文件上传失败，请重试。',
    'extension_not_allowed' => '这里不接受这种文件。允许的格式：:list。',
    'type_not_allowed' => '这个文件与它声称的格式不符，因此没有被保存。',
    'too_large' => '这个文件超过 :size。',
    'not_found' => '这个文件已不可用。',
];
