<?php
use Aza\Components\Thread\Thread;
use Aza\Components\Thread\ThreadPool;

use Devitek\Net\Torrent\Client\Transmission\Transmission284;
use Devitek\Net\Torrent\Seeder;
use Devitek\Net\Torrent\Torrent;

require __DIR__ . '/vendor/autoload.php';
/**
 * AzaThread examples
 *
 * @project Anizoptera CMF
 * @package system.thread
 * @author  Amal Samally <amal.samally at gmail.com>
 * @license MIT
 */
/**
 * Test thread
 */

class TestThreadReturnFirstArgument extends Thread
{
    var $stats;
    function formatSize ( $size, $precision = 2 ) {
        $units = array('b', 'Kb', 'Mb', 'Gb', 'Tb');
        while( ( $next = next( $units ) ) && $size > 1024 )
            $size /= 1024;
        return round( $size, $precision ) . ' ' . ( $next ? prev( $units ) : end( $units ) );
    }
    function leech($torrentFile){

        $stats = is_file('stats.db')?json_decode(file_get_contents('stats.db'),true):[];

        if(in_array(basename($torrentFile),$stats)) {
            return "skip: $torrentFile";
        }

        $torrent = new Torrent($torrentFile);
        $client  = new Transmission284();
        $seeder  = new Seeder($client, $torrent , rand(1,5) );
        
        $seeder->bind('update', function ($data) use ($torrent){
            echo  'name: ', $torrent->name(), 
                  ' | size: ', $this->formatSize( $torrent->size() ) ,
                 #' | hash info: ', $torrent->hash_info() ,
                  ' | uploaded:'. $this->formatSize( $data['uploaded'] ). ' | speed:' . $data['speed'] . ' MB/sec' . PHP_EOL;
        });

        $seeder->bind('error', function ($data) {
            echo $data['exception']->getMessage() . PHP_EOL;
        });

        try {
            $seeder->seed( ( $torrent->size() * 2 ) );
        } catch (Exception $e) {
            $e->getMessage();
        }

        $stats[] = basename($torrentFile);
        file_put_contents('stats.db',json_encode($stats , JSON_PRETTY_PRINT),LOCK_EX);

        return "done: $torrentFile";
    }
	/**
	 * {@inheritdoc}
	 */
	function process()
	{
        return $this->leech( $this->getParam(0) );
	}
}
// Checks
if (!Thread::$useForks) {
	echo PHP_EOL, "You do not have the minimum system requirements to work in async mode!!!";
	if (!Base::$hasForkSupport) {
		echo PHP_EOL, "You don't have pcntl or posix extensions installed or either not CLI SAPI environment!";
	}
	if (!EventBase::$hasLibevent) {
		echo PHP_EOL, "You don't have libevent extension installed!";
	}
	echo PHP_EOL;
}
// ----------------------------------------------

$threads  = 8;
$jobs     = glob('uTorrent/*.torrent');
$jobs_num = count($jobs);
echo PHP_EOL,
	"Example with pool of threads ($threads) and pool of jobs ($jobs_num)",
	PHP_EOL;
$pool = new ThreadPool('TestThreadReturnFirstArgument', $threads);
$num     = $jobs_num; // Number of tasks
$left    = $jobs_num; // Number of remaining tasks
$started = array();
do {
	while ($left > 0 && $pool->hasWaiting()) {
		$task = array_shift($jobs);
		if (!$threadId = $pool->run($task)) {
			throw new Exception('Pool slots error');
		}
		$started[$threadId] = $task;
		$left--;
	}
	if ($results = $pool->wait($failed)) {
		foreach ($results as $threadId => $result) {
			unset($started[$threadId]);
			$num--;
			echo "result: $result (thread $threadId)", PHP_EOL;
		}
	}
	if ($failed) {
		// Error handling here
		// processing is not successful if thread dies
		// when worked or working timeout exceeded
		foreach ($failed as $threadId => $err) {
			list($errorCode, $errorMessage) = $err;
			$jobs[] = $started[$threadId];
			echo "error: {$started[$threadId]} ",
				"(thread $threadId): #$errorCode - $errorMessage", PHP_EOL;
			unset($started[$threadId]);
			$left++;
		}
	}
} while ($num > 0);
// After work it's strongly recommended to clean
// resources obviously to avoid leaks
$pool->cleanup();
