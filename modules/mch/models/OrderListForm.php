<?php
/**
 * Created by IntelliJ IDEA.
 * User: luwei
 * Date: 2017/7/20
 * Time: 14:34
 */

namespace app\modules\mch\models;


use app\models\Goods;
use app\models\Order;
use app\models\OrderDetail;
use app\models\OrderRefund;
use app\models\Recharge;
use app\models\ReOrder;
use app\models\Share;
use app\models\Shop;
use app\models\User;
use app\modules\mch\extensions\Export;
use yii\data\Pagination;
use yii\helpers\ArrayHelper;
use app\models\GoodsPic;

class OrderListForm extends Model
{
    public $store_id;
    public $user_id;
    public $keyword;
    public $status;
    public $is_recycle;
    public $page;
    public $limit;

    public $flag;//是否导出
    public $is_offline;
    public $clerk_id;
    public $parent_id;
    public $shop_id;

    public $date_start;
    public $date_end;
    public $express_type;
    public $keyword_1;
    public $seller_comments;

    public function rules()
    {
        return [
            [['keyword',], 'trim'],
            [['status','is_recycle', 'page', 'limit', 'user_id', 'is_offline', 'clerk_id', 'shop_id', 'keyword_1'], 'integer'],
            [['status',], 'default', 'value' => -1],
            [['page',], 'default', 'value' => 1],
            //[['limit',], 'default', 'value' => 20],
            [['flag', 'date_start', 'date_end', 'express_type'], 'trim'],
            [['flag'], 'default', 'value' => 'no'],
            [['seller_comments'], 'string'],
        ];
    }

    public function search()
    {
        if (!$this->validate())
            return $this->getModelError();
        $query = Order::find()->alias('o')->where([
            'o.store_id' => $this->store_id,
            'o.mch_id' => 0,
        ])->leftJoin(['u' => User::tableName()], 'u.id = o.user_id');
        if ($this->status == 0) {//未付款
            $query->andWhere([
                'o.is_pay' => 0,
            ]);
        }
        if ($this->status == 1) {//待发货
            $query->andWhere([
                'o.is_send' => 0,
            ])->andWhere(['or',['o.is_pay'=>1],['o.pay_type'=>2]]);
        }
        if ($this->status == 2) {//待确认收货
            $query->andWhere([
                'o.is_send' => 1,
                'o.is_confirm' => 0,
            ])->andWhere(['or',['o.is_pay'=>1],['o.pay_type'=>2]]);
        }
        if ($this->status == 3) {//已确认收货
            $query->andWhere([
                'o.is_send' => 1,
                'o.is_confirm' => 1,
            ])->andWhere(['or',['o.is_pay'=>1],['o.pay_type'=>2]]);
        }
        if ($this->status == 4) {//售后
        }
        if ($this->status == 5) {//已取消订单
            $query->andWhere(['or', ['o.is_cancel' => 1], ['o.is_delete' => 1]]);
        } else {
            if($this->is_recycle!=1){
                $query->andWhere(['o.is_cancel' => 0, 'o.is_delete' => 0]);
            } 
        }
        if($this->status == 6){//申请取消待处理订单
            $query->andWhere(['o.apply_delete'=>1]);
        }
        if ($this->status != 5) {
            if ($this->user_id) {//查找指定用户的
                $query->andWhere([
                    'o.user_id' => $this->user_id,
                ]);
            }
            if ($this->clerk_id) {//查找指定核销员的订单
                $query->andWhere([
                    'o.clerk_id' => $this->clerk_id,
                ]);
            }
            if ($this->shop_id) {//查找指定门店的订单
                $query->andWhere([
                    'o.shop_id' => $this->shop_id,
                ]);
            }
            if ($this->parent_id) {
                $query->andWhere(['o.parent_id' => $this->parent_id]);
            }
        }
        if ($this->date_start) {
            $query->andWhere(['>=', 'o.addtime', strtotime($this->date_start)]);
        }
        if ($this->date_end) {
            $query->andWhere(['<=', 'o.addtime', strtotime($this->date_end) + 86400]);
        }

        if ($this->keyword) {//关键字查找
            if($this->keyword_1 == 1){
                $query->andWhere(['like', 'o.order_no', $this->keyword]);
            }
            if($this->keyword_1 == 2){
                $query->andWhere(['like', 'u.nickname', $this->keyword]);
            }
            if($this->keyword_1 == 3){
                $query->andWhere(['like', 'o.name', $this->keyword]);
            }
//            $query->andWhere([
//                'OR',
//                ['LIKE', 'o.id', $this->keyword],
//                ['LIKE', 'o.order_no', $this->keyword],
//                ['LIKE', 'o.name', $this->keyword],
//                ['LIKE', 'o.mobile', $this->keyword],
//                ['LIKE', 'o.address', $this->keyword],
//                ['LIKE', 'o.remark', $this->keyword],
//                ['LIKE', 'o.express_no', $this->keyword],
//                ['LIKE', 'u.nickname', $this->keyword],
//            ]);
        }
        if ($this->is_offline) {
            $query->andWhere(['o.is_offline' => $this->is_offline]);
        }

        //充值异常版本2.2.2.1
        $user_list = ReOrder::find()->alias('ro')->where(['ro.store_id'=>$this->store_id,'ro.is_pay'=>1])
            ->leftJoin(['r'=>Recharge::tableName()],'r.pay_price = ro.pay_price')
            ->andWhere(['>','ro.send_price',0])->andWhere('r.send_price != ro.send_price')->groupBy('ro.user_id')
            ->select(['ro.user_id'])->column();
        if($this->status == 7){//异常订单
            $query->andWhere(['o.user_id'=>$user_list,'o.pay_type'=>3]);
        }
        if($this->is_recycle==1){
            $query->andWhere(['o.is_recycle'=>1]);
        }else{
            $query->andWhere(['o.is_recycle'=>0]);
        }

        if ($this->flag == "EXPORT") {
            $query_ex = clone $query;
            $list_ex = $query_ex->select('o.*,u.nickname')->orderBy('o.addtime DESC')->asArray()->all();
            foreach ($list_ex as $i => &$item) {
                $item['goods_list'] = $this->getOrderGoodsList($item['id']);
            }
            Export::order_2($list_ex,$this->is_offline);
        }
        $count = $query->count();
        $pagination = new Pagination(['totalCount' => $count, 'pageSize' => $this->limit, 'page' => $this->page - 1]);
        
        $clerkQuery = User::find()
            ->select('nickname')
            ->where(['store_id' => $this->store_id])
            ->andWhere('id = o.clerk_id');

        $refundQuery = OrderRefund::find()
            ->select('status')
            ->where(['store_id' => $this->store_id, 'is_delete' => 0])
            ->andWhere('order_id = o.id')
            ->orderBy(['addtime' => SORT_DESC])
            ->limit(1);

        $list = $query->limit($pagination->limit)->offset($pagination->offset)->orderBy('o.addtime DESC')
            ->select(['o.*', 'u.nickname', 'clerk_name' => $clerkQuery, 'refund' => $refundQuery])->asArray()->all();

        $listArray = ArrayHelper::toArray($list);
        foreach ($listArray as $i => &$item) {
            $item['goods_list'] = $this->getOrderGoodsList($item['id']);
            // if ($item['is_offline'] == 1 && $item['is_send'] == 1) {
            //     $user = User::findOne(['id' => $item['clerk_id'], 'store_id' => $this->store_id]);
            //     $item['clerk_name'] = $user->nickname;
            // }

            //此处考虑将 Order 和 Shop 模型使用 hasOne 关联，查询时使用 with 预查询 -- wi1dcard
            if ($item['shop_id'] && $item['shop_id'] != 0) {
                $shop = Shop::find()->where(['store_id' => $this->store_id, 'id' => $item['shop_id']])->asArray()->one();
                $item['shop'] = $shop;
            }
            // $order_refund = OrderRefund::findOne(['store_id' => $this->store_id, 'order_id' => $item['id'], 'is_delete' => 0]);
            // $item['refund'] = "";
            // if ($order_refund) {
            //     $item['refund'] = $order_refund->status;
            // }
            $item['integral'] = json_decode($item['integral'], true);
            $item['flag'] = 0;
            if($item['pay_type'] == 3 && in_array($item['user_id'],$user_list)){
                $item['flag'] = 1;
            }
        }
        
        return [
            'row_count' => $count,
            'page_count' => $pagination->pageCount,
            'pagination' => $pagination,
            'list' => $listArray,
        ];

    }

    public function getOrderGoodsList($order_id)
    {
        $picQuery = GoodsPic::find()
            ->alias('gp')
            ->select('pic_url')
            ->andWhere('gp.goods_id = od.goods_id')
            ->andWhere(['is_delete' => 0])
            ->limit(1);
        $orderDetailList = OrderDetail::find()->alias('od')
            ->leftJoin(['g' => Goods::tableName()], 'od.goods_id=g.id')
            ->where([
                'od.is_delete' => 0,
                'od.order_id' => $order_id,
            ])->select(['od.*','g.name','g.unit','goods_pic' => $picQuery])->asArray()->all();
        foreach ($orderDetailList as $i => &$item) {
            //$goods = new Goods();
            //$goods->id = $item['goods_id'];
            //$item['goods_pic'] = $goods->getGoodsPic(0)->pic_url;
            $item['attr_list'] = json_decode($item['attr']);
        }
        return $orderDetailList;
    }

    public static function getCountData($store_id)
    {
        $form = new OrderListForm();
        $form->limit = 0;
        $form->store_id = $store_id;
        $data = [];

        $form->status = -1;
        $res = $form->search();
        $data['all'] = $res['row_count'];

        $form->status = 0;
        $res = $form->search();
        $data['status_0'] = $res['row_count'];

        $form->status = 1;
        $res = $form->search();
        $data['status_1'] = $res['row_count'];

        $form->status = 2;
        $res = $form->search();
        $data['status_2'] = $res['row_count'];

        $form->status = 3;
        $res = $form->search();
        $data['status_3'] = $res['row_count'];

        $form->status = 5;
        $res = $form->search();
        $data['status_5'] = $res['row_count'];

        return $data;
    }
}