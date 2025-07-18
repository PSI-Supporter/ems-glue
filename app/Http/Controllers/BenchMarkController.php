<?php

namespace App\Http\Controllers;

class BenchMarkController extends Controller
{
    public function benchmarkWithOpCache()
    {
        // Mulai pengukuran waktu
        $startTime = microtime(true);
        $memoryStart = memory_get_usage();

        // Operasi berat: perhitungan matematis
        $result = 0;
        for ($i = 0; $i < 1000000; $i++) {
            $result += sin($i) * cos($i) * tan($i);
        }

        // Operasi berat: manipulasi string
        $string = str_repeat("Lorem ipsum dolor sit amet", 1000);
        for ($i = 0; $i < 500; $i++) {
            $string = str_replace("ipsum", "replaced", $string);
        }

        // Operasi berat: manipulasi array
        $array = [];
        for ($i = 0; $i < 100000; $i++) {
            $array[] = rand(1, 1000);
        }
        $array = array_map(function ($value) {
            return $value * 2;
        }, $array);
        sort($array);


        $fibResult = $this->fibonacci(30);

        // Hitung waktu dan memori
        $executionTime = microtime(true) - $startTime;
        $memoryUsed = memory_get_usage() - $memoryStart;

        return response()->json([
            'status' => 'success',
            'opcache_enabled' => true,
            'execution_time' => $executionTime . ' seconds',
            'memory_used' => $memoryUsed / 1024 / 1024 . ' MB',
            'result' => $result,
            'fibonacci' => $fibResult,
        ]);
    }

    // Operasi berat: rekursi
    function fibonacci($n)
    {
        if ($n <= 1) return $n;
        return $this->fibonacci($n - 1) + $this->fibonacci($n - 2);
    }

    public function benchmarkWithoutOpCache()
    {
        // Nonaktifkan OPCache untuk pengujian ini
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }

        // Mulai pengukuran waktu
        $startTime = microtime(true);
        $memoryStart = memory_get_usage();

        // Operasi berat: perhitungan matematis
        $result = 0;
        for ($i = 0; $i < 1000000; $i++) {
            $result += sin($i) * cos($i) * tan($i);
        }

        // Operasi berat: manipulasi string
        $string = str_repeat("Lorem ipsum dolor sit amet", 1000);
        for ($i = 0; $i < 500; $i++) {
            $string = str_replace("ipsum", "replaced", $string);
        }

        // Operasi berat: manipulasi array
        $array = [];
        for ($i = 0; $i < 100000; $i++) {
            $array[] = rand(1, 1000);
        }
        $array = array_map(function ($value) {
            return $value * 2;
        }, $array);
        sort($array);

        $fibResult = $this->fibonacci(30);

        // Hitung waktu dan memori
        $executionTime = microtime(true) - $startTime;
        $memoryUsed = memory_get_usage() - $memoryStart;

        return response()->json([
            'status' => 'success',
            'opcache_enabled' => false,
            'execution_time' => $executionTime . ' seconds',
            'memory_used' => $memoryUsed / 1024 / 1024 . ' MB',
            'result' => $result,
            'fibonacci' => $fibResult
        ]);
    }
}
