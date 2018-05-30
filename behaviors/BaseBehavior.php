<?php
/**
 * Created by IntelliJ IDEA.
 * User: luwei
 * Date: 2018/1/18
 * Time: 17:07
 */

namespace app\behaviors;

use yii\base\Behavior;
use yii\web\Controller;

class BaseBehavior extends Behavior
{
    public function beforeActionBase($event)
    {
        if(static::doNotDoIt($this->only_routes)){
            return true;
        }
        return $this->beforeAction($event);
    }

    public function events()
    {
        return [
            Controller::EVENT_BEFORE_ACTION => 'beforeActionBase'
        ];
    }

    public static function doNotDoIt($only_routes)
    {
        $route = \Yii::$app->controller->route;
        if (is_array($only_routes)) {
            foreach ($only_routes as $r) {
                if ($r == $route) {
                    return true;
                }
                $r = str_replace('/', '\/', $r);
                $r = str_replace('*', '.*', $r);
                $r = "/^{$r}$/";
                if (preg_match($r, $route, $res)) {
                    return true;
                }
            }
        }
        return false;
    }
}