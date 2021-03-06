<?php
// +----------------------------------------------------------------------
// | A3Mall
// +----------------------------------------------------------------------
// | Copyright (c) 2020 http://www.a3-mall.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: xzncit <158373108@qq.com>
// +----------------------------------------------------------------------
namespace app\api\controller\wap;

use mall\utils\BC;
use mall\utils\Tool;
use think\facade\Db;
use think\facade\Request;

class Point extends Auth {

    public function index(){
        $page = Request::param("page","1","intval");
        $size = 10;


        $count = Db::name("promotion_point")
            ->alias('p')
            ->join("goods g","p.goods_id=g.id","LEFT")
            ->where('g.status',0)->count();


        $total = ceil($count/$size);
        if($total == $page -1){
            return $this->returnAjax("empty",-1,[]);
        }

        $result = Db::name("promotion_point")
            ->alias('p')
            ->field("p.id,g.title,g.photo,p.point as price,g.sale")
            ->join("goods g","p.goods_id=g.id","LEFT")
            ->where('g.status',0)
            ->order('g.id','desc')->limit((($page - 1) * $size),$size)->select()->toArray();

        $data = array_map(function ($rs){
            $rs["photo"] = Tool::thumb($rs["photo"],"medium",true);
            return $rs;
        },$result);

        return $this->returnAjax("ok",1, [
            "list"=>$data,
            "page"=>$page,
            "total"=>$total,
            "size"=>$size
        ]);
    }

    public function view(){
        $id = Request::param("id","0","intval");
        if(($promotion_point = Db::name("promotion_point")->where("id",$id)->find()) == false){
            return $this->returnAjax("积分商品不存在",0);
        }
        if(($goods = Db::name("goods")->where("id",$promotion_point["goods_id"])->where("status",0)->find()) == false){
            return $this->returnAjax("商品不存在",0);
        }

        $data = [];
        $data["activityId"] = $promotion_point["id"];
        $data["goods_id"] = $promotion_point["goods_id"];
        $data["collect"] = false;
        if(!empty($this->users)){
            $data["collect"] = Db::name("users_favorite")->where([
                "user_id"=>$this->users["id"],
                "goods_id"=>$goods["id"]
            ])->count() ? true : false;
        }

        $data["photo"] = array_map(function ($result){
            return Tool::thumb($result["photo"],"",true);
        }, Db::name("attachments")->field("path as photo")->where([
            "pid"=>$goods["id"],
            "module"=>"goods",
            "method"=>"photo"
        ])->select()->toArray());

        $promotionGroupItem = Db::name("promotion_point_item")
            ->where("pid",$id)->select()->toArray();

        $spec_key = [];
        foreach($promotionGroupItem as $v){
            $spec_key[] = $v["spec_key"];
        }

        $goods_item = Db::name("goods_item")
            ->where("spec_Key",'in',$spec_key)
            ->where("goods_id",$goods['id'])->select()->toArray();

        $goods_attribute = [];
        $___attr = [];
        foreach($goods_item as $val){
            $spec = explode(",",$val["spec_key"]);
            foreach($spec as $v){
                $spec_Key = explode(":",$v);
                if(!in_array($spec_Key[0].'_'.$spec_Key[1],$___attr)){
                    $___attr[] = $spec_Key[0].'_'.$spec_Key[1];
                    $goods_attribute[] = Db::name("goods_attribute")->where([
                        "goods_id"=>$goods["id"],
                        "attr_id"=>$spec_Key[0],
                        "attr_data_id"=>$spec_Key[1],
                    ])->find();
                }
            }
        }

        if(!empty($goods_attribute)){
            $attribute = [];
            foreach($goods_attribute as $key=>$val){
                if(empty($attribute[$val["attr_id"]])){
                    $attribute[$val["attr_id"]]['k'] = $val["name"];
                }
                $attribute[$val["attr_id"]]['v'][] = [
                    "id"=>$val["attr_id"].":".$val["attr_data_id"],
                    "name"=>$val["value"],
                ];
            }

            $goodsItem = Db::name("goods_item")->where([
                "goods_id"=>$goods["id"]
            ])->select()->toArray();

            $sku = [];
            $i=0;
            foreach($attribute as $key=>$value){
                $value["k_s"] = 's' . $i++;
                $sku[] = $value;
            }

            /**
            sku: {
            // 所有sku规格类目与其值的从属关系，比如商品有颜色和尺码两大类规格，颜色下面又有红色和蓝色两个规格值。
            // 可以理解为一个商品可以有多个规格类目，一个规格类目下可以有多个规格值。
            tree: [
            {
            k: '颜色', // skuKeyName：规格类目名称
            v: [
            {
            id: '30349', // skuValueId：规格值 id
            name: '红色', // skuValueName：规格值名称
            },
            {
            id: '1215',
            name: '蓝色',
            }
            ],
            k_s: 's1' // skuKeyStr：sku 组合列表（下方 list）中当前类目对应的 key 值，value 值会是从属于当前类目的一个规格值 id
            },
            {
            k: '大小', // skuKeyName：规格类目名称
            v: [
            {
            id: '303491', // skuValueId：规格值 id
            name: '红色', // skuValueName：规格值名称
            },
            {
            id: '12152',
            name: '蓝色',
            }
            ],
            k_s: 's2' // skuKeyStr：sku 组合列表（下方 list）中当前类目对应的 key 值，value 值会是从属于当前类目的一个规格值 id
            }
            ],
            // 所有 sku 的组合列表，比如红色、M 码为一个 sku 组合，红色、S 码为另一个组合
            list: [
            {
            id: 2259, // skuId，下单时后端需要
            price: 100, // 价格（单位分）
            s1: '1215', // 规格类目 k_s 为 s1 的对应规格值 id
            s2: '12152', // 规格类目 k_s 为 s2 的对应规格值 id
            s3: '0', // 最多包含3个规格值，为0表示不存在该规格
            stock_num: 110 // 当前 sku 组合对应的库存
            },
            {
            id: 2260, // skuId，下单时后端需要
            price: 1100, // 价格（单位分）
            s1: '30349', // 规格类目 k_s 为 s1 的对应规格值 id
            s2: '303491', // 规格类目 k_s 为 s2 的对应规格值 id
            s3: '0', // 最多包含3个规格值，为0表示不存在该规格
            stock_num: 10 // 当前 sku 组合对应的库存
            }
            ],
            price: '1.00', // 默认价格（单位元）
            stock_num: 227, // 商品总库存
            collection_id: 2261, // 无规格商品 skuId 取 collection_id，否则取所选 sku 组合对应的 id
            none_sku: false, // 是否无规格商品
            hide_stock: false // 是否隐藏剩余库存
            }
             */
            $item = [];
            foreach($goodsItem as $key=>$value){
                $item[$key]['id'] = $value["id"];
                $item[$key]['price'] = $promotion_point["point"] * 100;
                $arr = explode(",",$value["spec_key"]);
                foreach($sku as $k=>$v){
                    $item[$key][$v["k_s"]] = $arr[$k];
                }
                $item[$key]['stock_num'] = $promotion_point["store_nums"];
            }

            $data["sku"]["tree"] = $sku;
            $data["sku"]["list"] = $item;
        }else{
            $data["sku"]["tree"] = [];
            $data["sku"]["list"] = [];
        }

        $data["sku"]["price"] = $promotion_point["point"];
        $data["sku"]["stock_num"] = $promotion_point["store_nums"];
        $data["sku"]["collection_id"] = $goods["id"];
        $data["sku"]["none_sku"] = empty($goods_attribute) ? true : false;
        $data["sku"]["hide_stock"] = false;

        $goods["content"] = Tool::replaceContentImage(Tool::removeContentAttr($goods["content"]));

        $data["goods"] = [
            "title"=>$goods["title"],
            "photo"=>Tool::thumb($goods["photo"],'medium',true),
            "sell_price"=>$promotion_point["point"],
            "market_price"=>$goods["sell_price"],
            "store_nums"=>$promotion_point["store_nums"],
            "sale"=>$promotion_point["sum_count"],
            "content"=>$goods["content"]
        ];

        return $this->returnAjax("ok",1,$data);
    }

}