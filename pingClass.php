<?php
    class Ping{

    private $ipaddress;
    private $hostname;

    private $timeout;
    private $count;
    private $interval;

    public function __construct(){
        $this->parseOptions();
        $this->pingHost();
    }

    private function parseOptions(){

        $options = getopt('c:i:h:t:d:');

        // Packet Count:
        if(isset($options['c'])){
            $this->count = (int) $options['c'];
        } else {
            $this->count = 3;
        }

        // Send Interval:
        if(isset($options['i'])){
            $this->interval = (int) $options['i'];
        } else{
            $this->interval = 1;
        }

        // Wait Timeout:
        if(isset($options['t'])){
            $this->timeout = (int) $options['t'];
        } else{
            $this->timeout = 1;
        }

        // Destination Host:
        if(isset($options['h'])){
            if (filter_var($options['h'], FILTER_VALIDATE_IP)) {
                $this->hostname = $options['h'];
                $this->ipaddress = $options['h'];
            } else {
                $this->hostname = $options['h'];
                $this->ipaddress = gethostbyname($this->hostname);

                // Validate Lookup:
                if($this->hostname === $this->ipaddress){
                    die("ping: $this->hostname: Name or service not known" . PHP_EOL);
                }
            }
        } else{
            die("You must set the target host flag (-h)." . PHP_EOL);
        }
    }

    public function pingHost(){

        // received Packets (init).
        $received = 0;
        $record = array();

        // Socket Creation:
        $socket = socket_create(AF_INET, SOCK_RAW, getprotobyname('icmp'));

        // Socket Config:
        // http://php.net/manual/en/function.socket-set-option.php
        // https://notes.shichao.io/unp/ch7/#ipv4-socket-options
        socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, array('sec' => $this->timeout, 'usec' => 0));
        socket_connect($socket, $this->ipaddress, 0);

        echo "PING $this->hostname ($this->ipaddress)." . PHP_EOL;

        // Send $count packets:
        for($i = 0; $i < $this->count; $i++){

            $packet = Packet::buildPacket($i);

            // Send packet:
            socket_send($socket, $packet, strlen($packet), 0);
            $time = microtime(true);
            
            // Wait for reply.
            if($packet = socket_read($socket, 256)){

                $received++;

                $ttl        = Packet::getTTL($packet);
                $time       = Packet::getTime($time);
                $sequence   = Packet::getSeq($packet);

                // Print response:
                echo strlen($packet) . " bytes from $this->hostname ($this->ipaddress): ";
                echo "icmp_seq=$sequence ";
                echo "ttl=$ttl ";
                echo "time=$time ms";
                echo PHP_EOL;

                array_push($record, $time);

            }else{
                echo "Timeout" . PHP_EOL;
            }

            // Interval:
            if($i < $this->count-1){
                sleep($this->interval);
            }
        }

        socket_close($socket);
        $this->report($received, $record);
    }

    public function report($received, $record){

        // received Packets / Sent Packets.
        $packetloss = (1 - $received/$this->count) * 100;

        echo PHP_EOL;
        echo "--- $this->hostname statistics ---"   . PHP_EOL;

        echo $this->count . " packets transmitted, ";
        echo $received . " received, ";
        echo $packetloss . "% packet loss, ";
        echo "time " . array_sum($record) . " ms";

        echo PHP_EOL;

        $avg = round(array_sum($record)/$received, 3);
        $min = min($record);
        $max = max($record);

        echo "rtt min/avg/max = ";

        echo "$min/";
        echo "$avg/";
        echo $max;

        echo " ms";
        echo PHP_EOL;

        // Jitter
        $jitter = array();

        for($i = 1; $i < count($record) ; $i++){
            $delta = abs($record[$i] - $record[$i-1]);
            array_push($jitter, $delta);
        }

        $jitter = array_sum($jitter)/($received-1);

        echo "jitter = " . round($jitter, 3) . " ms";

        echo PHP_EOL;

        // Throughput:
        // Average RTT in seconds (ms/1000 -> s):
        $avgRTT = ($avg/1000);

        // Too Lazy to create a TCP conn. to calculate BW. Suppose a RWIN of 64K.
        $rwin = 65535;

        // Throughput in Bytes. Then convert to bits by * 8 (1 byte -> 8 bits).
        // Then convert bits to Mbits. (X / 1000 (Kbits) / 1000 (Mbits)).
        $throughput = (((($rwin / $avgRTT) * 8) / 1000) / 1000);

        // TODO: Weighted latency average for a more precise throughput approximation.
        echo "throughput approximation = " . round($throughput, 3) . " Mbps";
        echo PHP_EOL;     
    }
}



