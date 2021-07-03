<?php

namespace App\Console\Commands;

use App\Models\Mynavi;
use App\Models\MynaviJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ScrapeMynavi extends Command
{
    const HOST = 'https://tenshoku.mynavi.jp';
    const FILE_PATH = 'app/mynavi_jobs.csv';
    const PAGE_NUM = 10;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scrape:mynavi';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scrape Mynavi だよ';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->truncateTables();
        $this->saveUrls();
        $this->saveJobs();
        $this->exportCsv();
    }

    private function truncateTables()
    {
        DB::table('mynavis')->truncate();
        DB::table('mynavi_jobs')->truncate();
    }

    private function saveJobs()
    {
        foreach (Mynavi::all() as $mynaviUrl) {
            $url = $this::HOST . $mynaviUrl->url;
            $crawler = \Goutte::request('GET', $url);
            dump($url);

            MynaviJob::create([
                'url' => $url,
                'title' => $this->getTitle($crawler),
                'company_name' => $this->getComponyName($crawler),
                'features' => $this->getFeatures($crawler),
            ]);
            sleep(30);
        }
    }

    private function getTitle($crawler)
    {
        return $crawler->filter('.occName')->text();
    }

    private function getComponyName($crawler)
    {
        return $crawler->filter('.companyName')->text();
    }

    private function getFeatures($crawler)
    {
        $features = $crawler->filter('.cassetteRecruit__attribute.cassetteRecruit__attribute-jobinfo .cassetteRecruit__attributeLabel > span')->each(function ($node) {
            return $node->text();
        });

        return implode(',', $features);
    }

    private function saveUrls()
    {
        foreach (range(1, $this::PAGE_NUM) as $value) {
            $url = $this::HOST . '/list/pg' . $value . '/';
            $crawler = \Goutte::request('GET', $url);
            $urls = $crawler->filter('.cassetteRecruit__copy > a')->each(function ($node) {
                $href = $node->attr('href');
                return [
                    'url' => substr($href, 0, strpos($href, '/', 1) + 1),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            });
            DB::table('mynavis')->insert($urls);
            sleep(30);
        }
    }

    private function exportCsv()
    {
        $file = fopen(storage_path($this::FILE_PATH), 'w');
        if (!$file) {
            throw new \Exception('ファイルの作成に失敗しました');
        }

        if (!fputcsv($file, [
            'id',
            'url',
            'title',
            'company_name',
            'features',
        ])) {
            throw new \Exception('ヘッダの作成に失敗しました');
        };

        foreach (MynaviJob::all() as $value) {
            if (!fputcsv($file, [
                $value->id,
                $value->url,
                $value->title,
                $value->company_name,
                $value->features,
            ])) {
                throw new \Exception('ボディの作成に失敗しました');
            };
        }
        fclose($file);
    }
}
