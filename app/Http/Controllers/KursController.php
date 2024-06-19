<?php

namespace App\Http\Controllers;

use DateInterval;
use DatePeriod;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\DomCrawler\Crawler;

class KursController extends Controller
{

    public function __construct()
    {
        date_default_timezone_set('Asia/Jakarta');
    }

    function downloadKursKemenkeu($date, $kurs)
    {
        set_time_limit(3000);
        $guzz = new \GuzzleHttp\Client(['timeout' => 600, 'connect_timeout' => 600]);

        $res = $guzz->request('GET', "https://fiskal.kemenkeu.go.id/informasi-publik/kurs-pajak?date=" . date('Y-m-d', strtotime($date)), [
            'verify' => false,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Connection' => 'Close'
            ],
        ]);

        try {
            logger('start crawling for date ' . $date . ' and kurs ' . $kurs);

            $bodynya = $res->getBody();

            $crawler = new Crawler((string) $bodynya);

            $listItem = $crawler->filterXPath('//*[@class="table table-bordered table-striped"]/tbody/tr/td')->each(function ($value) use ($kurs) {

                $getText = $value->extract(['_text'])[0];

                $getText = trim(preg_replace('/\s\s+/', ' ', $getText));
                return $getText;
            });

            $hasil = [];
            $count = $keyMaster = 0;
            foreach ($listItem as $key => $value) {
                switch ($count) {
                    case 0:
                        $keyString = 'no';
                        break;

                    case 1:
                        $keyString = 'mata_uang';
                        break;

                    case 2:
                        $keyString = 'nilai';
                        break;

                    default:
                        $keyString = 'perubahan';
                        break;
                }

                $hasil[$keyMaster][$keyString] = $value;

                $count++;
                if ($count === 4) {
                    $count = 0;
                    $keyMaster++;
                }
            }

            $searchKurs = array_values(array_filter($hasil, function ($f) use ($kurs) {
                return str_contains($f['mata_uang'], $kurs);
            }))[0];

            return strtr($searchKurs['nilai'], ".,", ",.");
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            logger($e);
            return $e->getResponse()->getBody()->getContents();
        }
    }

    function getKurs(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'startDate' => 'required|date',
            'endDate' => 'required|date',
        ], [
            'startDate.required' => ':attribute is required',
            'startDate.date' => ':attribute should be date',
            'endDate.required' => ':attribute is required',
            'endDate.date' => ':attribute should be date',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 406);
        }

        $startDate = date_create($request->startDate);
        $endDate = date_create($request->endDate);
        $interval = DateInterval::createFromDateString('1 day');
        $datePeriod = new DatePeriod($startDate, $interval, $endDate);

        $data = [];
        foreach ($datePeriod as $d) {
            $data[] = [
                'date' => $d->format('Y-m-d'),
                'value' => str_replace(',', '', $this->downloadKursKemenkeu($d->format('Y-m-d'), 'USD'))
            ];
        }
        $data[] = [
            'date' => $request->endDate,
            'value' => str_replace(',', '', $this->downloadKursKemenkeu($request->endDate, 'USD'))
        ];

        $affectedRows = 0;
        DB::beginTransaction();
        try {
            foreach ($data as $r) {
                $SavedRecordCount = DB::table('MEXRATE_TBL')
                    ->where('MEXRATE_CURR', 'USD')
                    ->where('MEXRATE_TYPE', 'KMKEU')
                    ->where('MEXRATE_DT', $r['date'])
                    ->count();
                if ($SavedRecordCount == 0) {
                    $dataTobeSaved = [
                        'MEXRATE_CURR' => 'USD',
                        'MEXRATE_TYPE' => 'KMKEU',
                        'MEXRATE_DT' => $r['date'],
                        'MEXRATE_VAL' => $r['value'],
                        'MEXRATE_USRID' => 'sys',
                        'MEXRATE_LUPDT' => date('Y-m-d H:i:s'),
                    ];
                    $affectedRows += DB::table('MEXRATE_TBL')->insert($dataTobeSaved) ? 1 : 0;
                }
            }
            DB::commit();

            return ['message' => $affectedRows ? $affectedRows . ' row(s) saved successfully' : 'sorry , ther is something wrong'];
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()]);
        }


        return ['data' => $data];
    }

    function downloadKursDaily()
    {
        $SavedRecordCount = DB::table('MEXRATE_TBL')
            ->where('MEXRATE_CURR', 'USD')
            ->where('MEXRATE_TYPE', 'KMKEU')
            ->where('MEXRATE_DT', date('Y-m-d'))
            ->count();

        if ($SavedRecordCount == 0) {
            $data = [
                'MEXRATE_CURR' => 'USD',
                'MEXRATE_TYPE' => 'KMKEU',
                'MEXRATE_DT' => date('Y-m-d'),
                'MEXRATE_VAL' => str_replace(',', '', $this->downloadKursKemenkeu(date('Y-m-d'), 'USD')),
                'MEXRATE_USRID' => 'sys',
                'MEXRATE_LUPDT' => date('Y-m-d H:i:s'),
            ];
            DB::table('MEXRATE_TBL')->insert($data);
            return ['message' => 'downloaded successfully'];
        } else {
            return ['message' => 'already exist'];
        }
    }
}
