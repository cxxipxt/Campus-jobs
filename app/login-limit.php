<?php
// Fixed windows shared across sessions and PHP workers.
function employer_login_wait(string $username, string $address): int {
    $directory = sys_get_temp_dir().'/campusjobs-login-'.hash('sha256', __DIR__);
    if (!is_dir($directory) && !mkdir($directory,0700,true) && !is_dir($directory)) throw new RuntimeException('Cannot initialize login limiter');
    if (random_int(1,100)===1) {
        foreach (glob($directory.'/*') as $file) {
            if (filemtime($file)<time()-86400) @unlink($file);
        }
    }
    $wait = 0;
    foreach (['account:'.mb_strtolower(trim($username))=>10, 'ip:'.$address=>50] as $key=>$limit) {
        $path=$directory.'/'.hash('sha256',$key);
        $handle=fopen($path,'c+');
        if (!$handle) throw new RuntimeException('Cannot open login limiter');
        try {
            if (!flock($handle,LOCK_EX)) throw new RuntimeException('Cannot lock login limiter');
            $state=json_decode(stream_get_contents($handle),true);
            $now=time();
            if (!is_array($state) || ($state['until']??0)<=$now) $state=['until'=>$now+900,'count'=>0];
            if ($state['count'] >= $limit) $wait=max($wait,$state['until']-$now);
            else $state['count']++;
            rewind($handle);
            ftruncate($handle,0);
            fwrite($handle,json_encode($state));
            fflush($handle);
            flock($handle,LOCK_UN);
        } finally { fclose($handle); }
    }
    return $wait;
}
