<?php
require_once('vendor/autoload.php');

use Devitek\Net\Torrent\Client\Transmission\Transmission284;
use Devitek\Net\Torrent\Seeder;
use Devitek\Net\Torrent\Torrent;

$stats=is_file('stats.db')?json_decode(file_get_contents('stats.db'),true):array();
$date=date('d.m.Y');
if(!isset($stats[$date]))$stats[$date]=[];

foreach( glob('uTorrent/*.torrent') as $torrentFile){
    if(in_array(basename($torrentFile),$stats[$date])) {
        echo "skip:$torrentFile\n";
        continue;
    }
    $torrent = new Torrent($torrentFile);
    $client  = new Transmission284();
    $seeder  = new Seeder($client, $torrent , rand(1,5) );

    $seeder->bind('update', function ($data) use ($torrent){
        echo  'name: ', $torrent->name(), 
              ' | size: ', format( $torrent->size() ) ,
              #' | hash info: ', $torrent->hash_info() ,
              ' | uploaded:'. format( $data['uploaded'] ). ' | speed:' . $data['speed'] . ' MB/sec' . PHP_EOL;
    });

    $seeder->bind('error', function ($data) {
        echo $data['exception']->getMessage() . PHP_EOL;
    });

    try {
        $seeder->seed( ( $torrent->size() * 2 ) );
    } catch (Exception $e) {
        $e->getMessage();
    }

    $stats[$date][] = basename($torrentFile);
    file_put_contents('stats.db',json_encode($stats),LOCK_EX);
    
}

function format ( $size, $precision = 2 ) {
    $units = array('b', 'Kb', 'Mb', 'Gb', 'Tb');
    while( ( $next = next( $units ) ) && $size > 1024 )
        $size /= 1024;
    return round( $size, $precision ) . ' ' . ( $next ? prev( $units ) : end( $units ) );
}