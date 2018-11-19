<?php

class Ws
{

    CONST HOST = "0.0.0.0";
    CONST PORT = 8812;

    public $ws = null;

    public function __construct()
    {
        $this->ws = new swoole_websocket_server(self::HOST, self::PORT);

        $this->ws->set([
            'enable_static_handler' => true,
            'document_root' => "/var/www/swoole-live/public/static",
            'task_worker_num' => 5,
            'worker_num' => 5
        ]);

        $this->ws->on("open", [$this, 'onOpen']);
        $this->ws->on('message', [$this, 'onMessage']);
        $this->ws->on('request', [$this, 'onRequest']);
        $this->ws->on('workerstart', [$this, 'onWorkerStart']);
        $this->ws->on('task', [$this, 'onTask']);
        $this->ws->on('finish', [$this, 'onFinish']);
        $this->ws->on('close', [$this, 'onClose']);

        $this->ws->start();
    }

    public function onOpen($ws, $request)
    {
        var_dump($request->fd);
    }

    public function onMessage($ws, $frame)
    {
        echo "ser-push-message:{$frame->data}\n";
        $ws->push($frame->fd, 'server-push:' . date('Y-m-d H:i:s'));
    }

    public function onRequest($request, $response)
    {
        $_SERVER = [];
        if (isset($request->server)) {
            foreach ($request->server as $k => $v) {
                $_SERVER[strtoupper($k)] = $v;
            }
        }
        if (isset($request->header)) {
            foreach ($request->header as $k => $v) {
                $_SERVER[strtoupper($k)] = $v;
            }
        }
        $_GET = [];
        if (isset($request->get)) {
            foreach ($request->get as $k => $v) {
                $_GET[$k] = $v;
            }
        }
        $_FILES = [];
        if (isset($request->files)) {
            foreach ($request->files as $k => $v) {
                $_FILES[$k] = $v;
            }
        }
        $_POST = [];
        if (isset($request->post)) {
            foreach ($request->post as $k => $v) {
                $_POST[$k] = $v;
            }
        }
        $_POST['ws_server'] = $this->ws;

        ob_start();
        // 执行应用并响应
        think\Container::get('app', [APP_PATH])
            ->run()
            ->send();
        $res = ob_get_contents();
        ob_end_clean();
        $response->end($res);
    }

    public function onWorkerStart($server, $woker_id)
    {
        // 定义应用目录
        define('APP_PATH', __DIR__ . '/../application/');
        // 加载框架引导文件
        // require __DIR__ . '/../thinkphp/base.php';
        require __DIR__ . '/../thinkphp/start.php';
    }

    public function onTask($serv, $taskId, $wokerId, $data)
    {
        //分发task任务，不同任务对应不同逻辑
        $obj = new app\common\lib\task\Task();
        $method = $obj->$data['method'];
        $flag = $obj->$method[$data['data']];
        return $flag;


//        $Sms = new app\common\lib\ali\Sms();
//        try {
//            $response = $Sms::sendSms($data['phone'], $data['code']);
//        } catch (\Exception $e) {
//            echo $e->getMessage();
//        }
    }

    public function onFinish($serv, $taskId, $data)
    {
        echo "taskId:{$taskId}\n";
        echo "finish-data-success:{$data}\n";//$data为onTask的return内容
    }

    public function onClose($ws, $fd)
    {
        echo "Close:{$fd}\n";
    }
}

new Ws();