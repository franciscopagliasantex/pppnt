<?php
    class Packet{
        /**
         * Creates a valid ICMP Packet.
         * @param hex Starting sequence number.
         * @param hex Packet identifier number.
         */
        public static function buildPacket($seq = 0x0000, $id= 0x2107) {
            // ICMP Header Values:
            $type       = 0x08;
            $code       = 0x00;
            $checksum   = 0x0000;
            $identifier = $id;
            $sequence   = $seq;
            $data       = microtime();

            // Pack data into Binary string:
            $packet = pack( "CCnnnA*",
                            $type,
                            $code,
                            $checksum,
                            $identifier,
                            $sequence,
                            $data);

            // Validate Packet Length:
            $packet = self::validatePacketLength($packet);

            // Calculate IP Checksum for ICMP:
            // https://tools.ietf.org/html/rfc1071#section-4.1
            $checksum = self::calculateCheckSum($packet);

            // Pack Checksum into Binary string:
            $checksum = pack("n*", $checksum);

            // Replace empty Checksum (2 bytes) with calculated one:
            $packet[2] = $checksum[0];
            $packet[3] = $checksum[1];

            return $packet;
        }

        /**
         * Checks if Packet length is even. Appends padding byte
         * if length is odd.
         * @param string Binary string containing an ICMP Packet.
         */
        public static function validatePacketLength($packet) {
            // Get packet length:
            $packet_len = strlen($packet);

            // Length should be even. If odd, append byte.
            if ($packet_len % 2) {
                $packet .= "\x00";
            }

            return $packet;
        }

        /**
         * Calculates IP Checksum for an ICMP Packet.
         * https://tools.ietf.org/html/rfc1071#section-4.1
         * @param string Binary string containing an ICMP Packet.
         */
        public static function calculateCheckSum($packet) {
            // Unpack in 16 bit long string blocks.
            $bit = unpack('n*', $packet);

            // Perform binary addition.
            $sum = array_sum($bit);

            // Check for overflow.
            while($sum >> 16) {
                // Remove overflow + sum overflow.
                $sum = ($sum & 0xFFFF) + ($sum >> 16);
            }

            // One's complement.
            $checksum = (~$sum & 0xFFFF);

            return $checksum;
        }

        /**
         * Extracts TTL field from IP Header.
         * @param string Binary string containing an IP/ICMP (0 Reply) Packet.
         */
        public static function getTTL($packet) {
            // IP Header Position: 9 - 1 array index.
            return hexdec(bin2hex($packet[((9)-1)]));
        }

        /**
         * Extracts Sequence field from IP/ICMP Header.
         * @param string Binary string containing an IP/ICMP (0 Reply) Packet.
         */
        public static function getSeq($packet) {
            // Skip first 20 Bytes of IP Header.
            // Get 7th and 8th byte of the ICMP Header - 1 array index.
            return hexdec(bin2hex($packet[((20+7)-1)] . $packet[((20+8)-1)]));
        }
        
        /**
         * Calculares a new timestamp representing the RTT.
         * @param timestamp Timestamp in Microseconds from when the echo was sent.
         */
        public static function getTime($time) {
            // Time diff. converted from micro to mili. Round with 2 Decimals.
            return round((microtime(true) - $time) * 1000, 2);
        }
    }
?>