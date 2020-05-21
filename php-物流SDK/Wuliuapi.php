<?php
//1.1
namespace app\server\controller;

use think\Controller;
use think\Request;
use think\Db;
use app\server\controller\Redis;
use app\server\controller\SessionServer;
use think\Session;
use think\Cache;
use think\Log;
/*
    系统自带的函数
*/

use think\captcha\Captcha;
class Wuliuapi extends Controller
{

    const KUAIDINIAO_ID='';
    const KUAIDINIAO_KEY='';
    const KUAIDINIAO_URL='http://api.kdniao.com/Ebusiness/EbusinessOrderHandle.aspx';
	//版本：2.5.2

   static public function index($type=null,$OBJECT=null){

            /** 
             * 快递鸟 免费仅支持500次/天，仅支持3种快递类型
             * http://www.kdniao.com/api-track
             * @param $OBJECT = [
             *           'ShipperCode',    //申通/中通/圆通
             *           'LogisticCode'    //快递单号
             *       ]
             * @return json
             */
            $kuaidiniao                   = function ()use (&$OBJECT){
                /*
                    CREATE TABLE `vc_api_kdniao_api_track` (
                     `id` int(11) NOT NULL AUTO_INCREMENT,
                     `ShipperCode` varchar(20) NOT NULL COMMENT '快递类型',
                     `LogisticCode` varchar(20) NOT NULL COMMENT '快递单号',
                     `json` text NOT NULL,
                     `addtime` int(10) NOT NULL,
                     `changetime` int(10) NOT NULL,
                     `state` int(1) NOT NULL DEFAULT '0' COMMENT '0/1,1时为签收状态',
                     PRIMARY KEY (`id`)
                    ) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8
                */
                $data=db::table('vc_api_kdniao_api_track')
                ->where('LogisticCode',$OBJECT['LogisticCode'])
                ->find();
                if(!empty($data)){
                    if($data['state']==1){
                        return json_encode(['code'=>'10000','msg'=>json_decode($data['json'],true)]);
                    }
                    //冷却3小时
                    if($data['changetime']+10800>time()){
                        return json_encode(['code'=>'10000','msg'=>json_decode($data['json'],true)]);
                    }
                }
                    switch ($OBJECT['ShipperCode']) {
                        case '申通':
                            $OBJECT['ShipperCode']='STO';
                            break;
                        case '圆通':
                            $OBJECT['ShipperCode']='YTO';
                            break;
                        case '中通':
                        case '中通快递':
                            $OBJECT['ShipperCode']='ZTO';
                            break;
                        default:
                            return json_encode(['code'=>'40000','msg'=>'不支持快递类型']);
                            break;
                    }
                     /**
                     * Json方式 查询订单物流轨迹
                     */
                    $getOrderTracesByJson=function($OBJECT) use(&$sendPost,&$encrypt){
                        $requestData= "{'ShipperCode':'".$OBJECT["ShipperCode"]."','LogisticCode':'".$OBJECT['LogisticCode']."'}";
                        $datas = array(
                            'EBusinessID' => self::KUAIDINIAO_ID,//用户id
                            'RequestType' => '1002',
                            'RequestData' => urlencode($requestData) ,
                            'DataType' => '2',
                        );
                        $datas['DataSign'] = $encrypt($requestData, self::KUAIDINIAO_KEY);//API key
                        $result=$sendPost(self::KUAIDINIAO_URL, $datas);   
                        
                        //根据公司业务处理返回的信息......
                        return $result;
                    };
                     
                    /**
                     *  post提交数据 
                     * @param  string $url 请求Url
                     * @param  array $datas 提交的数据 
                     * @return url响应返回的html
                     */
                    $sendPost = function($url, $datas) {
                        $temps = array();   
                        foreach ($datas as $key => $value) {
                            $temps[] = sprintf('%s=%s', $key, $value);      
                        }   
                        $post_data = implode('&', $temps);
                        $url_info = parse_url($url);
                        if(empty($url_info['port']))
                        {
                            $url_info['port']=80;   
                        }
                        $httpheader = "POST " . $url_info['path'] . " HTTP/1.0\r\n";
                        $httpheader.= "Host:" . $url_info['host'] . "\r\n";
                        $httpheader.= "Content-Type:application/x-www-form-urlencoded\r\n";
                        $httpheader.= "Content-Length:" . strlen($post_data) . "\r\n";
                        $httpheader.= "Connection:close\r\n\r\n";
                        $httpheader.= $post_data;
                        $fd = fsockopen($url_info['host'], $url_info['port']);
                        fwrite($fd, $httpheader);
                        $gets = "";
                        $headerFlag = true;
                        while (!feof($fd)) {
                            if (($header = @fgets($fd)) && ($header == "\r\n" || $header == "\n")) {
                                break;
                            }
                        }
                        while (!feof($fd)) {
                            $gets.= fread($fd, 128);
                        }
                        fclose($fd);  
                        
                        return $gets;
                    };

                    /**
                     * 电商Sign签名生成
                     * @param data 内容   
                     * @param appkey Appkey
                     * @return DataSign签名
                     */
                   $encrypt = function($data, $appkey) {
                        return urlencode(base64_encode(md5($data.$appkey)));
                    };
                    $logisticResult=$getOrderTracesByJson($OBJECT);
                     if(json_decode($logisticResult,true)['State']==3){
                        $TSstate=1;
                        }else{
                        $TSstate=0;    
                        }
                     if(!empty($data)){
                        if($TSstate==0){
                            db::table('vc_api_kdniao_api_track')
                            ->where('LogisticCode',$OBJECT['LogisticCode'])
                            ->where('ShipperCode',$OBJECT['ShipperCode'])
                            ->update([
                                'changetime'    => time(),
                                'json'          => $logisticResult,
                            ]);
                        }else{
                            db::table('vc_api_kdniao_api_track')
                            ->where('LogisticCode',$OBJECT['LogisticCode'])
                            ->where('ShipperCode',$OBJECT['ShipperCode'])
                            ->update([
                                'changetime'    => time(),
                                'json'          => $logisticResult,
                                'state'         => '1'
                            ]);
                        }
                       }else{
                            db::table('vc_api_kdniao_api_track')->insert([
                                'ShipperCode'   => $OBJECT["ShipperCode"],
                                'LogisticCode'  => $OBJECT['LogisticCode'],
                                'addtime'       => time(),
                                'changetime'    => time(),
                                'json'          => $logisticResult,
                                'state'         => $TSstate
                            ]);
                       }
                    return json_encode(['code'=>'10000','msg'=>json_decode($logisticResult,true)]);
                };
            //配置方法
            $engine                  = function ($ENGINE_type=null,$ENGINE_OBJECT=null) use (&$OBJECT,&$type,&$engine){
                // 默认入口
                $default_entrance   = "index";
                /**
                 *    allow_alias        |       allow_full         |       结果
                 *       true            |         true             |   方法失效返回null
                 *       true            |         false            |     仅支持别名
                 *       false           |         true             |     仅支持全名
                 *       false           |         false            |   都支持(默认设置)
                 */
                //别名开关,详细看上表
                $allow_full                 = true; //不允许全名         false
                $allow_alias                = false; //不允许别名         false
                // 跳过全名检查
                $allow_full_not_run         =[
                    "ChangObject_array",
                ];
                // 别名定义,仅支持一维数组，方法名为键，别名为值，值必须小写,否则为无效注册
                $alias              = [
                    "kuaidiniao"          => "快递鸟",
                    ""
                    ];
                //ip黑白名单
                //白名单非空时，黑名单不生效
                //白名单为只允许限定ip访问，黑名单为不允许限定ip访问
                $ip_list             = [
                    'white' =>[
                        // '127.0.0.1'
                    ],
                    'black' =>[
                        // '127.0.0.1'
                    ]
                ];
                // 禁止目录
                $exclude            = [
                    "engine"
                    ];
                // 语句合法性完整性检查开关(需要消耗服务器一定性能)(不检查eval会不安全而且会报错)
                // 关闭请用注释
                preg_match('/^[^?]*/', $_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'],$eval_list_val);
                $eval_list      =[
                        "ChangObject"   => [
                            "array"     =>  $eval_list_val[0].'?type=ChangObject_array&OBJECT=',//.$OBJECT
                        ],
                    ];
                // 初始化运行方法列表(不建议修改，包括顺序调整，除非你会改)
                // 如果不需要某些功能，可以将该功能注释
                // 全名判断前执行
                $Initialization_List_Full_Name_Judgment_Before   =[
                    "IpListWhiteOrBlack_run",
                    "EasyAccessMethod",
                ];
                //全名判断后执行
                $Initialization_List_Full_Name_Judgment_After   =[
                    "MainContrast",
                    "default_entrance_Run",
                    "exclude_Run",
                    "ChangObject",
                ];
                // 禁止ChangObject方法
                $Disable_ChangObject_List  =[
                    "ChangObject_array",
                ];
                // 简易入口(内测)
                $EasyAccessMethod      = function() use (&$OBJECT,&$type,$engine){
                    $thisvalue = $_SERVER['QUERY_STRING'];
                    preg_match('{^[^&]*}',$thisvalue,$empty);
                    $iseasy=preg_match('{^easy!}',$thisvalue);
                    if(!empty($empty[0])&&$iseasy>0){
                            $type=urldecode(substr($thisvalue,stripos($thisvalue,'!')+1,stripos($thisvalue,'/')-stripos($thisvalue,'!')-1));
                            $OBJECT =urldecode(substr($thisvalue,stripos($thisvalue,'/')+1));
                            return ;
                    }
                    $isfalse=strpos($empty[0],'=');
                    if(!empty($empty[0])&&$isfalse===false){
                         $QUERY_STRING=explode('/',$empty[0]);
                         if(count($QUERY_STRING)==2||count($QUERY_STRING)==1){
                            $num=0;
                            foreach ($QUERY_STRING as $key => $value) {
                                if(!empty($value)){
                                    switch ($num) {
                                        case 0:
                                            $type = urldecode($value);
                                            break;
                                        case 1:
                                            $OBJECT = urldecode($value);
                                            break;
                                    }
                                }
                                $num++;
                            }
                            return ;
                        }
                    }
                    
                    
                    };
                //ip黑白名单执行
                $IpListWhiteOrBlack_run                             = function() use ($engine){
                    if(!empty($engine("ip_list")['white'])){
                        if(!in_array($_SERVER['REMOTE_ADDR'],$engine("ip_list")['white'])){
                            echo "非法访问";
                            die;
                        }
                    }else if(!empty($engine("ip_list")['black'])){
                        if(in_array($_SERVER['REMOTE_ADDR'],$engine("ip_list")['black'])){
                            echo "非法访问";
                            die;
                        }
                    }
                    };
                // 初始化运行方法
                // 全名判断前
                $Initialization_Run_Full_Name_Judgment_Before    = function() use ($engine){
                    foreach ($engine("Initialization_List_Full_Name_Judgment_Before") as $key => $value) {
                        $engine($value);
                    }
                    };
                // 全名判断后
                $Initialization_Run_Full_Name_Judgment_After    = function() use ($engine){
                    foreach ($engine("Initialization_List_Full_Name_Judgment_After") as $key => $value) {
                        $engine($value);
                    }
                    };
                // 禁止目录运行方法
                $exclude_Run            = function() use (&$type,$engine){
                    if(in_array($type, $engine("exclude"))){
                        $type = null;
                    };
                    };
                // 默认入口运行方法
                $default_entrance_Run   = function() use (&$type,$engine){
                    if(empty($type)){
                        $type = $engine("default_entrance");
                    };
                    };
                // object改变方法
                $ChangObject        = function() use (&$OBJECT,&$type,$engine){
                    if(in_array($type, $engine("Disable_ChangObject_List"))){
                        return;
                    }
                    if(is_array($OBJECT)){
                        return;
                    }
                    preg_match_all("{@([a-zA-z_]*?)@(.*?)@!@}",$OBJECT,$return);
                        if(!empty($return[0])){
                                switch ($return[1][0]) {
                                    case 'array':
                                        if(isset($engine('eval_list')['ChangObject']['array'])){
                                                $ch = curl_init ();
                                                curl_setopt($ch, CURLOPT_URL, $engine('eval_list')['ChangObject']['array'].$OBJECT);
                                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
                                                curl_exec($ch);
                                                $httpCode = curl_getinfo($ch,CURLINFO_HTTP_CODE);
                                                if($httpCode != 200){
                                                    return null;
                                                }
                                            }

                                        preg_match("/[^\[]*(\[.[<\x{4e00}-\x{9fa5}>0-9a-zA-Z_'!,\]\[]*\])[^\]]*/u", $return[2][0], $preg_match);
                                        if (!empty($preg_match[1])) {
                                            $what =str_replace('!', '=>', $preg_match[1]);
                                            eval('$return2 = '.$what.';');
                                        }

                                       break;
                                    case 'json':
                                        $json_decode=json_decode(htmlspecialchars_decode($return[2][0]),true);
                                        if(empty($json_decode)){
                                            break;
                                        }
                                        $return2=$json_decode;
                                        break;
                                    default:
                                       break;
                                }
                             if(!isset($return2)){
                                $return2=$return[2][0];
                            }
                            $OBJECT=$return2;
                       }
                    };
                // 别名支持方法
                $MainContrast       = function() use (&$type,$engine){
                    if($engine("allow_alias")){return null;}
                    $FindValue  =   strtolower($type);
                    $arr        =   $engine("alias");
                    $is_bool=array_search($FindValue,$arr);
                        if(is_bool($is_bool)){
                            foreach($arr as $key => $value){
                                if(is_array($value)){
                                    $is_bool=array_search($FindValue,$value);
                                }
                                if(!is_bool($is_bool)){
                                    $return=$key;
                                    break;
                                }
                            }
                            if(empty($return)){$return=null;}
                        }else{
                            $return=$is_bool;
                        }
                        if(!empty($return)){
                            $type=$return;
                        };
                    };
                return isset($ENGINE_type)?(is_object(${$ENGINE_type})?${$ENGINE_type}($ENGINE_OBJECT):${$ENGINE_type}):null;
                };
            //ChangObject内array验证方法
            $ChangObject_array               = function () use ($OBJECT){
                preg_match_all("{@([a-zA-z_]*?)@(.*?)@!@}",$OBJECT,$return);
                preg_match("/[^\[]*(\[.[<\x{4e00}-\x{9fa5}>0-9a-zA-Z_'!,\]\[]*\])[^\]]*/u", $return[2][0], $preg_match);
                    if (!empty($preg_match[1])) {
                        $what =str_replace('!', '=>', $preg_match[1]);
                        eval('$return2 = '.$what.';');
                    }
                };
            $engine("Initialization_Run_Full_Name_Judgment_Before");
            //全名判断
            if($engine("allow_full")&&isset(${$type})&&!in_array($type,$engine('allow_full_not_run'))){return null;};
            //引擎运行
            $engine("Initialization_Run_Full_Name_Judgment_After");

            //唯一入口
            return isset(${$type}) ? ${$type}() : null;
            }     
                    
}
    

