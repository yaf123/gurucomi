<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\GurucomiBaseSearchService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TwitterReplyBot extends Command
{
    private $service;

    /**
     * The name and signature of the console command.
     * 
     * 　呼び出すときの名前  php artisan TwitterReplyBot:Execute
     * 
     * @var string
     */
    protected $signature = 'TwitterReplyBot:Execute';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'グルメ関連ツイートユーザに同じ地域のコンテンツを返信ツイートする。';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(GurucomiBaseSearchService $service)
    {
        parent::__construct();
        $this->service = $service;
    }

    /**
     * Execute the console command.
     *　バッチのメイン処理
     * 
     * @return int
     */
    public function handle()
    {

        try {
            //都道府県ランダムで人気のお店の代表緯度経度取得
            $gurucomi_bases = $this->service->getGurucomibasesrnd();

            // 返信ツイート済みユーザを取得⇒$doneusers
            $doneusers = [];
            if (Storage::exists('doneusers.json')) {
                $wk = Storage::get("doneusers.json");
                $doneusers = json_decode($wk, true);
            }



//          #region返信ツイートユーザ候補取得⇒$target_users
            $target_users = [];
            foreach ($gurucomi_bases as $gurucomi_base) {

                $spot_lat = $gurucomi_base['lat'];
                $spot_lng = $gurucomi_base['lng'];

                if (!$spot_lat || !$spot_lng) {
                    print("no data <br>\n");
                    continue;
                }

                $wk = \Common::searchTweetUsers($spot_lat, $spot_lng);
                $target_users = array_merge($target_users, $wk);
            }
//          #endregion


            //返信ツイートユーザ候補でループ
            $replytweetcnt = 0;
            foreach ($target_users as $user) {

                if (isset($doneusers[$user['user']])) {
                    //すでに返信ツイートしていたらスキップ
                    print("zumi user " . $user['user'] . "<br>\n");
                    continue;
                }

                $user_lat = $user['lat'];
                $user_lng = $user['lng'];

                //返信ツイートユーザ候補の位置に近いspotを取得
                $gurucomi_bases_spots = $this->service->getGurucomibases4latlng($user_lat, $user_lng);


//              #region返信ツイートユーザ候補の位置に近いspotを1つ取得⇒$spot 
                $arround10 = array();
                $arround5 = array();
                foreach ($gurucomi_bases_spots as $key => $spot) {
                    $distance = $spot['distance'];
                    if ($distance > 10) {
                        continue;
                    } else {
                        if ($distance <= 5) {
                            $arround5[] = $spot;
                            $arround10[] = $spot;
                        } else {
                            $arround10[] = $spot;
                        }
                    }
                }
                if (count($arround5) >= 3) {
                    $gurucomi_bases_spots = $arround5;
                } else {
                    if (count($arround10) >= 3) {
                        $gurucomi_bases_spots = $arround10;
                    }
                }

                $key = rand(0, count($gurucomi_bases_spots) - 1);
                $spot = $gurucomi_bases_spots[$key];
//              #endregion



//              #regionデータ状況チェック　キャッチコピー生成⇒$spot
                if (!$spot['photo3_url']) {
                    //3枚目まで写真がない場合はスキップ
                    continue;
                }

                $catch = \Common::set_ryaku_touten($spot['catch']);
                if (!intval($spot['ggcomi_base_id']) || !$catch) {
                    //ちょうどよいキャッチコピーが作れない場合はスキップ
                    continue;
                }
                $spot['catch'] = $catch;

                $cat_name = "";
                $place_types = config("settei.PLACE_TYPES");
                if (array_key_exists($spot["type1"], $place_types)) {
                    $cat_name = $place_types[$spot["type1"]];
                } else if (array_key_exists($spot["type2"], $place_types)) {
                    $cat_name = $place_types[$spot["type2"]];
                } else if (array_key_exists($spot["type3"], $place_types)) {
                    $cat_name = $place_types[$spot["type3"]];
                } else {
                }
                $spot["cat_name"] = $cat_name;
//              #endregion



                // 対象スポットを使って返信ツイートユーザ候補に返信ツイート
                \Common::ReplyTweetforSpot($user, $spot);

                $doneusers[$user['user']] = 1;
                sleep(10);
                $replytweetcnt++;
                if ($replytweetcnt >= config("settei.BOT_REPLY_TWEET_CNT")) {
                    break;
                }
            }


            // 返信ツイート済みユーザを保存
            Storage::put("doneusers.json", json_encode($doneusers));

            print(date("Y/m/d (D) H:i:s", time()) . "<br>\n");

            $ret = 0;
        } catch (\Exception $e) {
            print_r("異常発生:" . $e->getFile() . ":" . $e->getLine() . ":" . $e->getMessage() . "\n");
            $ret = 1;
        }

        //メッセージ表示
        print_r('処理終了');

        return $ret;
    }
}
