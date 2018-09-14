
安装
----------
`composer require apetab/vbot-voice-translation`

配置
----------
```
/*
 * 拓展配置
 * ==============================
 * 如果加载拓展则必须加载此配置项
 */
'extension' => [
    // 管理员配置（必选），优先加载 remark_name
    'admin' => [
        'remark'   => '',
        'nickname' => '',
    ],
    'voice_translation' => [
        'voice_format' => 'pcm', // 转码格式支持pcm,amr, pcm文件较大,amr文件很小，两者声音质量听觉上差不多
        'qiniu_access_key' => '', //七牛云access_key
        'qiniu_secret_key' => '', //七牛云secret_key
        'qiniu_bucket_name' => 'voice', //七牛云bucket_name
        'qiniu_pipeline' => 'voice', //七牛云多媒体队列名称
        'baidu_app_id' => '11809276', //百度app_id
        'baidu_api_key' => 'qcGDESylpIhFDa6HZHUdz3rT', //百度api_key
        'baidu_secret_key' => 'lxxxVKdrfDnZgqgQMZ8UWgybdZV5YZTC', // 百度secret_key
    ],
],
```
