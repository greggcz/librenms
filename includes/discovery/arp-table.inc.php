<?php

unset($mac_table);

echo 'ARP Table : ';

$ipNetToMedia_data = snmp_walk($device, 'ipNetToMediaPhysAddress', '-Oq', 'IP-MIB');
$ipNetToMedia_data = str_replace('ipNetToMediaPhysAddress.', '', trim($ipNetToMedia_data));
$ipNetToMedia_data = str_replace('IP-MIB::', '', trim($ipNetToMedia_data));

echo 'Caching SQL tables ';
$mac_table_db = array();
foreach (dbFetchRows("SELECT M.* from ipv4_mac AS M, ports as I WHERE M.port_id = I.port_id and I.device_id = '".$device['device_id']."'") as $entry) {
    $mac_table_db[$entry['ipv4_address']] = $entry;
}
echo ' ipv4_mac ';
$all_ports = get_all_ports_cache($device['device_id']);
echo ' ports ' . "\n";
$delayed_update = array();
d_echo($mac_table_db);
$ignored_interfaces = array();
foreach (explode("\n", $ipNetToMedia_data) as $data) {
    list($oid, $mac) = explode(' ', $data);
    list($if, $first, $second, $third, $fourth) = explode('.', $oid);
    if($ignored_interfaces[$if]) {
        continue;
    }
    $ip = $first.'.'.$second.'.'.$third.'.'.$fourth;
    if ($ip != '...') {
        $interface = $all_ports[$if];
        if(!is_array($interface)) {
            $ignored_interfaces[$if] = true;
            continue;
        }
        list($m_a, $m_b, $m_c, $m_d, $m_e, $m_f) = explode(':', $mac);
        $m_a  = zeropad($m_a);
        $m_b  = zeropad($m_b);
        $m_c  = zeropad($m_c);
        $m_d  = zeropad($m_d);
        $m_e  = zeropad($m_e);
        $m_f  = zeropad($m_f);
        $md_a = hexdec($m_a);
        $md_b = hexdec($m_b);
        $md_c = hexdec($m_c);
        $md_d = hexdec($m_d);
        $md_e = hexdec($m_e);
        $md_f = hexdec($m_f);
        $mac  = "$m_a:$m_b:$m_c:$m_d:$m_e:$m_f";

        $mac_table[$if][$mac]['ip']       = $ip;
        $mac_table[$if][$mac]['ciscomac'] = "$m_a$m_b.$m_c$m_d.$m_e$m_f";
        $clean_mac = $m_a.$m_b.$m_c.$m_d.$m_e.$m_f;
        $mac_table[$if][$mac]['cleanmac'] = $clean_mac;
        $port_id = $interface['port_id'];
        $mac_table[$port_id][$clean_mac] = 1;

        if (isset($mac_table_db[$ip]) && $mac_table_db[$ip]['port_id'] == $interface['port_id']) {
            // Commented below, no longer needed but leaving for reference.
            // $sql = "UPDATE `ipv4_mac` SET `mac_address` = '$clean_mac' WHERE port_id = '".$interface['port_id']."' AND ipv4_address = '$ip'";
            $old_mac = $mac_table_db[$ip]['mac_address'];

            if ($clean_mac != $old_mac && $clean_mac != '' && $old_mac != '') {
                d_echo("Changed mac address for $ip from $old_mac to $clean_mac\n");

                log_event("MAC change: $ip : ".mac_clean_to_readable($old_mac).' -> '.mac_clean_to_readable($clean_mac), $device, 'interface', $interface['port_id']);
                dbUpdate(array('mac_address' => $clean_mac), 'ipv4_mac', 'port_id=? AND ipv4_address=?', array($interface['port_id'], $ip));
            }
            echo '.';
        }
        else if (isset($interface['port_id'])) {
            echo '+';
            // echo("Add MAC $mac\n");
            $insert_data = array(
                            'port_id'      => $interface['port_id'],
                            'mac_address'  => $clean_mac,
                            'ipv4_address' => $ip,
                           );
            array_push($delayed_update, $insert_data);
        }//end if
    }//end if
}//end foreach
dbBulkInsertUpdate($delayed_update, 'ipv4_mac');

//$sql = "SELECT * from ipv4_mac AS M, ports as I WHERE M.port_id = I.port_id and I.device_id = '".$device['device_id']."'";
//foreach (dbFetchRows($sql) as $entry) {
foreach ($mac_table_db as $entry) {
    $entry_mac = $entry['mac_address'];
    $entry_if  = $entry['port_id'];
    if (!$mac_table[$entry_if][$entry_mac]) {
        dbDelete('ipv4_mac', '`port_id` = ? AND `mac_address` = ?', array($entry_if, $entry_mac));
        d_echo("Removing MAC $entry_mac from interface ".$interface['ifName']);

        echo '-';
    }
}

echo "\n";
unset($mac);
unset($ignored_interfaces);
unset($mac_table_db);
unset($delayed_update);
unset($all_ports);