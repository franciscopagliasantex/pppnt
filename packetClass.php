<?php

class Packet{

    public static function buildPacket($seq = 0x0000, $id= 0x2107){

        // ICMP Header Values:
        $type       = 0x08;
        $code       = 0x00;
        $checksum   = 0x0000;
        $identifier = $id;
        $sequence   = $seq;
        $data       = microtime();

        // Pack into Binary String:
        $packet = pack( "CCnnnA*",
                        $type,
                        $code,
                        $checksum,
                        $identifier,
                        $sequence,
                        $data);

        // Validate Packet Length:
        $packet = self::validatePacketLength($packet);


        // Calculate Checksum:
        // https://tools.ietf.org/html/rfc1071#section-4.1
        $checksum = self::calculateCheckSum($packet);

        // Pack Checksum:
        $checksum = pack("n*", $checksum);

        // Replace Empty Checksum (2 bytes) with Calculated one:
        $packet[2] = $checksum[0];
        $packet[3] = $checksum[1];

        // Return Packet:
        return $packet;
    }

    public static function validatePacketLength($packet){

        // Get packet length:
        $packet_len = strlen($packet);

        // Length should be par. If odd, append byte.
        if($packet_len % 2){
            $packet .= "\x00";
        }

        // Return validated packet.
        return $packet;
    }

    public static function calculateCheckSum($packet){

        // Unpack in 16 bit long strings.
        $bit = unpack('n*', $packet);

        // Perform binary addition.
        $sum = array_sum($bit);

        // Check for overflow.
        while($sum >> 16){
            // Remove overflow + Sum overflow.
            $sum = ($sum & 0xFFFF) + ($sum >> 16);
        }

        // Return result complement.
        return (~$sum & 0xFFFF);
    }

    public static function getTTL($packet){
        // Byte 9 de la cabezera IP, -1 posición del index del array.
        return hexdec(bin2hex($packet[((9)-1)]));
    }

    public static function getSeq($packet){
        // Byte 7 y 8 de la cabezera ICMP.
        // Salteamos 20 bytes cabecera IP.
        // -1 posición index array.
        return hexdec(bin2hex($packet[((20+7)-1)] . $packet[((20+8)-1)]));
    }
    
    public static function getTime($time){
        // Resta el tiempo actual con el tiempo en el que el paquete fue enviado.
        // Se pasa de microsegundos a milisegundos, se dejan 2 decimales.
        return round((microtime(true) - $time) * 1000, 2);
    }
}

