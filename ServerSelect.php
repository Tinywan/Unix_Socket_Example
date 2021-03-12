<?php

class Worker
{
    // 监听Socket 套接字
    private $socket = NULL;

    // 所有客户端
    public $clients = NULL;

    // 连接回调事件
    public $onConnect = NULL;

    // 连接接收消息回调事件
    public $onMessage = NULL;

    public $onClose = NULL;

    public function __construct(string $socket_address)
    {
        //获取tcp协议号码。
        $tcp = getprotobyname('tcp');
        // 创建Socket服务
        $sock = stream_socket_server($socket_address); // 返回类型：resource(5) of type (stream)
        if (!$sock) {
            throw new Exception("failed to create socket: " . socket_strerror($sock) . "\n");
        }
        stream_set_blocking($sock, 0); // 程序无阻塞，0是非阻塞，1是阻塞
        $this->socket = $sock;
        // 注册第一个客户端
        $this->clients[(int) $this->socket] = $this->socket; // 5
        echo "listen on $socket_address ... \n";
    }

    public function runAll()
    {
        while (true) {
            $reads = $writes = $except = [];
            $reads =  $this->clients;

            // 反馈状态，是可以读取的客户端，系统底层自动修改
            stream_select($reads,  $writes,  $except, 60);

            // var_dump($reads); // 准备读取,客户端没刷新一次，就会增加一个客户端

            foreach ($reads as $key => $_sock) {
                // 是服务端
                if ($_sock == $this->socket) {
                    $newClient = stream_socket_accept($this->socket); // 返回类型：resource(6) of type (stream)
                    if ($newClient === false) {
                        unset($this->clients[$key]);
                        continue;
                    }
                    $this->clients[$key + 1] = $newClient;
                    if ($this->onConnect) {
                        call_user_func($this->onConnect, $newClient);
                    }
                } else {
                    // 读取客户端信息
                    $msg = @stream_socket_recvfrom($_sock, 2048); // 返回http协议的内容
                    if (!$msg) {
                        stream_socket_shutdown($this->clients[$key], STREAM_SHUT_RDWR);
                        unset($this->clients[$key]);
                        if ($this->onClose) {
                            call_user_func($this->onClose, $key);
                        }
                    } else {
                        if ($this->onMessage) {
                            call_user_func($this->onMessage, $_sock, $msg);
                        }
                    }
                }
            }
        }
    }
}

$worker = new Worker('tcp://0.0.0.0:9503');
$worker->onConnect = function () {
    echo '一个新的连接 ' . PHP_EOL;
};

$worker->onMessage = function ($conn, $message) {
    // 接收客户端消息
    // echo '这是一个信息的消息：' . json_encode($message).PHP_EOL;
    // 回复给客户端消息，必须保持协议一致，浏览器才会有响应
    $data = '这是服务端发送的消息';
    $responseMsg = rtrim($message, "\r\n");
    $httpResponse = "HTTP/1.1 200\r\n";
    $httpResponse .= "Connection: keep-alive\r\n";
    $httpResponse .= "Content-Type: text/html;charset=UTF-8\r\n";
    $httpResponse .= "Server: Unix Socket\r\n";
    $httpResponse .= "Content-length: " . strlen($data) . "\r\n\r\n";
    $httpResponse .= $data;
    @stream_socket_sendto($conn, $httpResponse);
};

$worker->onClose = function ($client_id) {
    echo "$client_id close \n";
};

$worker->runAll();
