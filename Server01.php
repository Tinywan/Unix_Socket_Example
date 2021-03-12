<?php

class Worker
{
    // 监听Socket 套接字
    protected $socket = NULL;

    // 连接回调事件
    public $onConnect = NULL;

    // 连接接收消息回调事件
    public $onMessage = NULL;

    public function __construct(string $socket_address)
    {
        //获取tcp协议号码。
        $tcp = getprotobyname('tcp');
        // 创建Socket服务
        $sock = stream_socket_server($socket_address); // 返回类型：resource(5) of type (stream)
        if(!$sock) {
            throw new Exception("failed to create socket: ".socket_strerror($sock)."\n");
        }
        // stream_set_blocking($sock,0); // 程序无阻塞，0是非阻塞，1是阻塞
        $this->socket = $sock;
        echo "listen on $socket_address ... \n";
    }

    public function runAll()
    {
        while(true) {
            $newClient = stream_socket_accept($this->socket); // 返回类型：resource(6) of type (stream)
            if($this->onConnect){
                // 连接事件，触发连接回调函数
                call_user_func($this->onConnect, $newClient);
            }
            // var_dump($this->socket,$client);

            // 读取客户端信息
            // $msg = fread($newClient,65535); // 返回http协议的内容
            $msg = @stream_socket_recvfrom($newClient,2048); // 返回http协议的内容
            if($this->onMessage){
                var_dump($msg);
                call_user_func($this->onMessage, $newClient,$msg);
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
    $httpResponse .= "Content-length: ".strlen($data)."\r\n\r\n";
    $httpResponse .= $data;
    @stream_socket_sendto($conn,$httpResponse);
};

$worker->runAll();
