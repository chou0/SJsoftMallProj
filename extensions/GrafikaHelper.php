<?php
/**
 * Created by IntelliJ IDEA.
 * User: luwei
 * Date: 2017/12/20
 * Time: 14:06
 */

namespace app\extensions;


class GrafikaHelper
{
    /**
     * 获取支持的图片处理库
     * @return array
     */
    public static function getSupportEditorLib()
    {
        switch(true){
            case function_exists('gd_info'):
            return ['Gd'];
            case class_exists('\Imagick') && method_exists((new \Imagick()), 'setImageOpacity'):
            return ['Imagick'];
            default:
            return ['Gd'];
        }
        // if (function_exists('gd_info'))
        //     return ['Gd'];
        // if (class_exists('\Imagick')) {
        //     $tmp_imagick = new \Imagick();
        //     if (method_exists($tmp_imagick, 'setImageOpacity')) {
        //         $editor_lib = ['Imagick'];
        //     } else {
        //         $editor_lib = ['Gd'];
        //     }
        // } else {
        //     $editor_lib = ['Gd'];
        // }
        // return $editor_lib;
    }
}