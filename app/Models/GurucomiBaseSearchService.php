<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\GurucomiBase;
use App\Models\GurucomiBaseGeo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Abraham\TwitterOAuth\TwitterOAuth;

/**
 * GurucomiBaseテーブル関連の検索サービス
 */
final class GurucomiBaseSearchService
{

    /**
     * 都道府県ランダムで人気のお店取得
     *
     * @return array gurucomibases
     *
     */
    public function getGurucomibasesrnd()
    {

        $SQL_cat_in = config("settei.BOT_TARGET_CAT");
        $pref_arr = config("settei.BOT_TARGET_PREF");
        $pref_key = array_rand($pref_arr, 1);
        $SQL_pref = $pref_arr[$pref_key];
        $gurucomi_bases = GurucomiBase
            ::where('administrative_area_level_1', $SQL_pref)
            ->where('rating', '>=', 4)
            ->where('photo_count', '>=', 5)
            ->where('review_count', '>=', 3)
            ->where(function ($query) use ($SQL_cat_in) {
                $query->whereIn('type1', $SQL_cat_in)->orWhereIn('type2', $SQL_cat_in)->orWhereIn('type3', $SQL_cat_in);
            })
            ->orderByRaw('RAND()')
            ->limit(5)
            ->get()
            ->toArray();
        return $gurucomi_bases;
    }

    /**
     * 緯度経度に近い人気のお店取得
     *
     * @param string $lat
     * @param string $lng
     * @return array gurucomibases
     *
     */
    public function getGurucomibases4latlng($lat, $lng)
    {
        $SQL_cat_in = config("settei.BOT_TARGET_CAT");
        $pref_arr = config("settei.BOT_TARGET_PREF");
        $pref_key = array_rand($pref_arr, 1);
        $SQL_pref = $pref_arr[$pref_key];
        $query = GurucomiBaseGeo
            ::selectRaw("cid, GLENGTH( GEOMFROMTEXT( CONCAT( 'LineString( $lng $lat , ', X( geopoint ) ,  ' ', Y( geopoint ) ,  ')' )))  * 112.12 AS distance")
            ->where('disp', 1)
            ->where('lat', '>', 1)
            ->where('lng', '>', 1)
            ->orderByRaw('distance', 'asc')
            ->limit(50);

        //print($query->toSql());
        $gurucomi_base_geos = $query->get()->toArray();

        $cid_arr = array();
        foreach ($gurucomi_base_geos as $cid_item) {
            $cid_arr[$cid_item['cid']] = $cid_item['distance'];
        }
        $gurucomi_bases_spots = GurucomiBase
            ::where('administrative_area_level_1', $SQL_pref)
            ->whereIn('cid', array_keys($cid_arr))
            ->where('rating', '>=', 3.5)
            ->where('photo_count', '>=', 5)
            ->where('review_count', '>=', 3)
            ->where(function ($query) use ($SQL_cat_in) {
                $query->whereIn('type1', $SQL_cat_in)->orWhereIn('type2', $SQL_cat_in)->orWhereIn('type3', $SQL_cat_in);
            })
            ->get()
            ->toArray();
        //print_r($gurucomi_bases_spots);exit;
        foreach ($gurucomi_bases_spots as $key => $spot) {
            //print($pref);
            $cid = $spot['cid'];
            $gurucomi_bases_spots[$key]['distance'] = $cid_arr[$cid];
        }

        return $gurucomi_bases_spots;
    }
}
