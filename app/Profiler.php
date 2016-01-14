<?php

class Profiler
{

    public static function show()
    {
        if(defined("PROFILER") && PROFILER) {
            global $profilerClassAllocatedSize, $profilerStartTime;
            $end = round(microtime(true) - $profilerStartTime, 2);
            $request = Core::$request;

            echo '<div id="profiler" translate=no>';
                echo '<h1>Queeraz Profiler <i class="fa fa-arrow-down"></i></h1>';
                echo '<div class="data">';
                    echo '<h2>Benchmarks</h2>';
                    echo '<div><span>Time to execute</span>'.$end.' sec</div>';
                    echo '<div><span>Database queries</span>'.MySQL::$_new_queries.'</div>';
                    echo '<div><span>Database queries (cached)</span>'.MySQL::$_cached_queries.'</div>';
                    echo '<h2>Request Info</h2>';
                    echo '<div><span>Controller</span>'.$request['controller'].'</div>';
                    echo '<div><span>Action</span>'.$request['action'].'</div>';
                    echo '<div><span>Id</span>'.$request['id'].'</div>';
                    if(!empty($request['params'])) {
                        echo '<div><span>Params</span>';
                        foreach($request['params'] as $k => $v) {
                            echo $k.' => '.$v.'<br/>';
                        }
                        echo '</div>';
                    }
                    echo '<h2>Memory usage</h2>';
                    echo '<div><span>Memory usage real:</span>'.self::convert(memory_get_usage(true)).'</div>';
                    echo '<div><span>Memory usage emalloc:</span>'.self::convert(memory_get_usage()).'</div>';
                    echo '<div><span>Memory peak real:</span>'.self::convert(memory_get_peak_usage(true)).'</div>';
                    echo '<div><span>Memory peak emalloc:</span>'.self::convert(memory_get_peak_usage()).'</div>';
                    echo '<h2>Defined Classes (+emalloc mem)</h2>';
                    $totalSize = 0;
                    foreach ($profilerClassAllocatedSize as $name => $size) {
                        $totalSize += $size;
                        echo '<div><span>'.$name.'</span>'.self::convert($size).'</div>';
                    }
                    echo '<div>-------------</div>';
                    echo '<div><span>Total class mem emalloc</span>'.self::convert($totalSize).'</div>';
                echo '</div>';
            echo '</div>';
            echo '<script type="text/javascript">$("#profiler h1").on("click", function() { $(this).find("i").toggleClass("fa-arrow-up fa-arrow-down"); $("#profiler .data").toggle(); });</script>';
        }
    }

    private static function convert($size)
    {
        $unit=array('b','kb','mb','gb','tb','pb');
        return @round($size/pow(1024,($i=floor(log($size,1024)))),2).' '.$unit[$i];
    }
}
