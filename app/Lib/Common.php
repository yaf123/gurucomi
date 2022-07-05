<?php

namespace App\Lib;

use App;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Abraham\TwitterOAuth\TwitterOAuth;

class Common
{

    /**
     * お店の情報を近隣のtwitterユーザに返信ツイート
     *
     * @param array $user
     * @param array $spot
     *
     */
    public function ReplyTweetforSpot($user, $spot)
    {

        $to = new TwitterOAuth(
            config("twitter.TWITTER_CLIENT_KEY"),
            config("twitter.TWITTER_CLIENT_SECRET"),
            config("twitter.TWITTER_CLIENT_ID_ACCESS_TOKEN"),
            config("twitter.TWITTER_CLIENT_ID_ACCESS_TOKEN_SECRET")
        );

        //写真をアップ
        $res = array();
        $media_ids = array();

        $photo_cache_binary = file_get_contents($spot["photo3_url"]);

        if ($photo_cache_binary) {
            $res = $to->OAuthRequest(
                "https://upload.twitter.com/1.1/media/upload.json",
                "POST",
                array("media_data" => base64_encode($photo_cache_binary))
            );
            $res = json_decode($res, true);
            //print_r($res);
        }

        if ($res["media_id"]) {
            $media_ids[] = $res["media_id"];
        }

        //リクエスト頻度下げる
        sleep(100);

        //返信ツイートランダムで言い回しを変える。
        $gurucomi_domain = config('settei.GURUCOMI_DOMAIN');
        $gurucomi_alias = config('settei.GURUCOMI_ALIAS');
        if (rand(0, 1) == 1) {
            $message = ".@{$user['user']} ここもいいですよ！ \n→ " .
                "{$spot['cat_name']} {$spot['name']}\n「{$spot['catch']}」\n" .
                "https://{$gurucomi_domain}/{$gurucomi_alias}/?id={$spot['ggcomi_base_id']} " .
                "{$spot['administrative_area_level_1']}{$spot['locality']}{$spot['sublocality_level_1']}";
        } else {
            $message = ".@{$user['user']}さんにオススメ！ \n→ " .
                "{$spot['cat_name']}{$spot['name']}\n「{$spot['catch']}」\n" .
                "https://{$gurucomi_domain}/{$gurucomi_alias}/?id={$spot['ggcomi_base_id']} " .
                "{$spot['administrative_area_level_1']}{$spot['locality']}{$spot['sublocality_level_1']}";
        }

        //print_r($user);

        if ($media_ids) {
            $res = $to->OAuthRequest(
                "https://api.twitter.com/1.1/statuses/update.json",
                "POST",
                array("status" => $message, "media_ids" => implode(",", $media_ids),)
            );
        } else {
            $res = $to->OAuthRequest(
                "https://api.twitter.com/1.1/statuses/update.json",
                "POST",
                array("status" => $message)
            );
        }
        $res = json_decode($res, true);
        //print_r($res);
    }

    /**
     * 緯度経度周辺でのツイートしているユーザ情報を取得
     *
     * @param string $spot_lat
     * @param string $spot_lng
     * @return array
     *
     */
    public function searchTweetUsers($spot_lat, $spot_lng)
    {

        $ret_users = [];

        $to = new TwitterOAuth(
            config("twitter.TWITTER_CLIENT_KEY"),
            config("twitter.TWITTER_CLIENT_SECRET"),
            config("twitter.TWITTER_CLIENT_ID_ACCESS_TOKEN"),
            config("twitter.TWITTER_CLIENT_ID_ACCESS_TOKEN_SECRET")
        );

        $search = implode(" OR ", config("settei.BOT_TARGET_KEYWORD"));

        $res = $to->OAuthRequest(
            "https://api.twitter.com/1.1/search/tweets.json",
            "GET",
            array(
                "geocode" => "$spot_lat,$spot_lng,100km",
                "locale" => "ja",
                "count" => 100, 'q' => $search,
                'result_type' => 'mixed'
            )
        );

        $res = (array)json_decode($res, true);

        if (!isset($res['statuses'])) {
            return [];
            exit;
        }

        foreach ($res['statuses'] as $tweet) {

            if (!$tweet['place']) {
                //print("no place<br>\n");
                continue;
            }

            //抽出したtwitterのユーザのフォロワー数を確認。100以上のみ。位置ツイートから住所を取得 function_cache
            if ($tweet['user']['friends_count'] >= 100) {
            } else {
                //print("no follow<br>\n");
                continue;
            }

            if (count($tweet['place']['bounding_box']['coordinates'][0]) != 4) {
                continue;
            }

            $lat_total = 0;
            $lng_total = 0;
            foreach ($tweet['place']['bounding_box']['coordinates'][0] as $latlng) {
                $lng_total += $latlng[0];
                $lat_total += $latlng[1];
            }
            $lng = round($lng_total / 4, 6);
            $lat = round($lat_total / 4, 6);
            //print_r($tweet['place']['bounding_box']['coordinates'][0]);
            //print("$lat, $lng<br>\n");

            if (!$lat || !$lng) {
                //print("no lat no lng<br>\n");
                continue;
            }

            if ($tweet['user']['screen_name'] == "guru_comi") {
                continue;
            }

            $wk = array();
            $wk['text'] = $tweet['text'];
            $wk['lat'] = $lat;
            $wk['lng'] = $lng;
            $wk['spot_lat'] = $spot_lat;
            $wk['spot_lng'] = $spot_lng;
            $wk['user'] = $tweet['user']['screen_name'];
            $wk['tweet_id'] = $tweet['id'];
            $wk['tweet_url'] = "http://twitter.com/" . $wk['user'] . "/status/" . $wk['tweet_id'];

            $ret_users[] = $wk;
        }

        return $ret_users;
    }

    /**
     * 長い文字列から区切りのいいところまでを抽出して読点を付ける。
     *
     * @param string $catch
     * @return string
     *
     */
    public function set_ryaku_touten($catch)
    {
        $catch = preg_replace("/(.*?)(。|\!|！|\?|？).*/ius", '$1$2', $catch);

        if (mb_strlen($catch, "UTF-8") >= 30) {
            $catch_wk = preg_replace("/.*?、(.*)/ius", '$1', $catch);

            if (mb_strlen($catch_wk, "UTF-8") >= 8) {
                $catch = $catch_wk;
            }
        }

        if (mb_strlen($catch, "UTF-8") >= 30) {
            $catch_wk = preg_replace("/.*?、(.*)/ius", '$1', $catch);

            if (mb_strlen($catch_wk, "UTF-8") >= 8) {
                $catch = $catch_wk;
            }
        }

        if (preg_match('/。$/ius', $catch)) {
        } else {
            if (preg_match('/[ぁ-んァ-ヶー一-龠]+$/ius', $catch)) {
                //最後が日本語だったら
                if (preg_match("/[\!！\?？😋]+$/ius", $catch)) {
                } else {
                    $catch .= "。";
                }
            }
        }

        if (mb_strlen($catch, "UTF-8") > config("settei.RYAKKU_MOJI")) {
            $catch = mb_substr($catch, 0, config("settei.RYAKKU_MOJI"), "UTF-8") . "...";
        }

        return $catch;
    }



}
