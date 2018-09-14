<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/9/14
 * Time: 10:54
 */

namespace Apetab\VbotVoice;


use Hanson\Vbot\Extension\AbstractMessageHandler;
use Hanson\Vbot\Foundation\Vbot;
use Hanson\Vbot\Message\Text;
use Hanson\Vbot\Message\Voice;
use Hanson\Vbot\Support\File;
use Illuminate\Support\Collection;
use Qiniu\Auth;
use function Qiniu\base64_urlSafeEncode;
use Qiniu\Processing\PersistentFop;
use Qiniu\Storage\UploadManager;

class VoiceTranslation extends AbstractMessageHandler
{
    public $author = 'ZhangSir';

    public $version = '1.0.0';

    public $zhName = '语音转文字';

    public $name = 'voice_translation';

    const OPS_MAP = [
        'pcm' => 'avthumb/s16le/ab/16k/ar/8000/ac/1|saveas/',
        'amr' => 'avthumb/amr|saveas/'
    ];

    const RATE_MAP = [
        'pcm' => 8000,
        'amr' => 8000
    ];

    private $message;

    /**
     * @var Vbot
     */
    private $vbot;

    /**
     * @var Auth
     */
    private $auth;

    private $token;

    /**
     * @var UploadManager
     */
    private $updateManager;

    /**
     * @var AipSpeech
     */
    private $aipSpeech;

    /**
     * @var PersistentFop
     */
    private $persistentFop;

    /**
     * 注册拓展时的操作.
     */
    public function register()
    {
        $this->vbot = vbot();
        $format = $this->config['voice_format'];
        $bucket =  $this->config['qiniu_bucket_name'];
        $saveName = base64_urlSafeEncode("{$bucket}:$(fprefix).{$format}");
        $this->auth = new Auth($this->config['qiniu_access_key'], $this->config['qiniu_secret_key']);

        $policy = [
            'persistentPipeline' => $this->config['qiniu_pipeline'],
            'persistentOps' => self::OPS_MAP[$format].$saveName
        ];

        $this->token = $this->auth->uploadToken($bucket, null, 3600, $policy);

        $this->persistentFop = new PersistentFop($this->auth);
        $this->updateManager = new UploadManager();
        $this->aipSpeech = new AipSpeech($this->config['baidu_app_id'], $this->config['baidu_api_key'], $this->config['baidu_secret_key']);
    }

    /**
     * 开发者需要实现的方法.
     *
     * @param Collection $collection
     *
     * @return mixed
     * @throws \Hanson\Vbot\Exceptions\ArgumentException
     */
    public function handler(Collection $collection)
    {
        if ($collection['fromType'] != 'Self' || $collection['type'] != 'voice') return;
        $this->message = $collection;
        Voice::download($collection, [$this, 'translation']);

        return;
    }

    /**
     * @param $resource
     * @throws \Exception
     */
    public function translation($resource)
    {
        $format = $this->config['voice_format'];
        $fileName = $this->message['raw']['MsgId'].'.mp3';
        $filePath = vbot('config')['user_path'].'/voice/';

        File::saveTo($filePath.$fileName, $resource);

        list($ret, $err) = $this->updateManager->putFile($this->token, $fileName, $filePath.$fileName);

        $persistentId = $ret['persistentId'];
        $times = 6;
        while ($times--) {
            list($ret, ) = $this->persistentFop->status($persistentId);
            if ($ret['code'] == 0) break;
            if ($ret['code'] == 1) continue;

            $this->vbot->console->log($ret['items'][0]['error']);
            return;
        }

        $url = "http://pezntfalu.bkt.clouddn.com/{$ret['items'][0]['key']}";
        $signedUrl = $this->auth->privateDownloadUrl($url);
        $voice = $this->vbot->http->get($signedUrl);

        $ret = $this->aipSpeech->asr($voice, $format, self::RATE_MAP[$format]);

        if ($ret['err_no'] != 0) return;

        if (isset($this->message['raw']['ToUserName'])) {
            Text::send($this->message['raw']['ToUserName'], $ret['result'][0]);
        }
    }
}
