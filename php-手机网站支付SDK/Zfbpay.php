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
use \app\common\logic\Buy_1;
// 常用
// session::get("member_id","LogonStatus"),
// session::get("member_name","LogonStatus"),
use think\captcha\Captcha;


class zfbpay extends Controller
{
    const RSAPRIVATEKEY='';
    //注意此处有坑
    const ALIPAYRSAPUBLICKEY='';
    const APPID='2016101300674048';
    const GATEWAYURL='https://openapi.alipaydev.com/gateway.do';
    const SELLER_ID='2088102179336413';
    const NOTIFTURL='http://tleomic.applinzi.com';
    
	//版本：2.5.2
    //127.0.0.1/mc_md/public/server/zfbpay?phonepay
   static public function index($type=null,$OBJECT=null){
            //https://opendocs.alipay.com/apis/api_1/alipay.trade.wap.pay
            //对应 alipay.trade.wap.pay(手机网站支付接口2.0)
            /*  $OBJECT = [
                    'subject'       => 商品名,
                    'out_trade_no'  => 订单号,
                    'total_amount'  => 金额,
                    'jumpurl'       => 回调地址//选填
                ] */
            $phonepay                = function() use (&$OBJECT){
                if(empty($OBJECT['subject'])){
                    return '缺少商品名:subject';
                }
                if(empty($OBJECT['out_trade_no'])){
                    return '缺少单号:out_trade_no';
                }
                if(empty($OBJECT['total_amount'])){
                    return '缺少金额:total_amount';
                }
                if(empty($OBJECT['jumpurl'])){
                    $jumpurl=urlencode('http://127.0.0.1/mc_md/public/mendian/order/justorder?id='.$OBJECT['out_trade_no']);
                }else{
                    $jumpurl=urlencode($OBJECT['jumpurl'].$OBJECT['out_trade_no']);
                }
                vendor('aop.AopClient');
                vendor('aop.request.AlipayTradeWapPayRequest');
                        $aop = new \AopClient ();
                        $aop->gatewayUrl = self::GATEWAYURL;
                        $aop->appId = self::APPID;
                        $aop->rsaPrivateKey = self::RSAPRIVATEKEY;
                        $aop->alipayrsaPublicKey= self::ALIPAYRSAPUBLICKEY;
                        $aop->apiVersion = '1.0';
                        $aop->signType = 'RSA2';
                        $aop->postCharset='UTF-8';
                        $aop->format='json';
                        $request = new \AlipayTradeWapPayRequest ();
                        $request->setNotifyUrl(self::NOTIFTURL);
                        // $request->setReturnUrl('http://tleomic.applinzi.com/?type=w');
                        $request->setBizContent("{" .
                        "\"subject\":\"".$OBJECT['subject']."\"," .//名称
                        "\"out_trade_no\":\"".$OBJECT['out_trade_no']."\"," .//订单号
                        "\"total_amount\":".$OBJECT['total_amount']."," .//金额
                        "\"seller_id\":\"".self::SELLER_ID."\"," .//商家支付宝id
                        // "\"passback_params\":\"http%3A%2F%2Ftleomic.applinzi.com%2F%3Fw\"," .// 公用回传参数，如果请求时传递了该参数，则返回给商户时会回传该参数。支付宝只会在同步返回（包括跳转回商户网站）和异步通知时将该参数原样返回。本参数必须进行UrlEncode之后才可以发送给支付宝。
                        "\"quit_url\":\"http://tleomic.applinzi.com?easy!jump/".$jumpurl."\"," .//用户付款中途退出返回商户网站的地址           此处我用的是我的网站跳回本地
                        "\"product_code\":\"".$OBJECT['out_trade_no']."\"" .//销售产品码，商家和支付宝签约的产品码
                        "  }");
                        $result = $aop->pageExecute ( $request);
                        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
                        return  $result;
                };
            //https://opendocs.alipay.com/apis/api_1/alipay.trade.query
            //对应 alipay.trade.query(统一收单线下交易查询)
            /* $OBJECT = 订单号 */
            $getpaymsg               = function() use (&$OBJECT){
                if(empty($OBJECT)){
                    return '缺少单号';
                }
                vendor('aop.AopClient');
                vendor('aop.request.AlipayTradeQueryRequest');
                $aop = new \AopClient ();   
                $aop->gatewayUrl = self::GATEWAYURL;
                $aop->appId = self::APPID;
                $aop->rsaPrivateKey = self::RSAPRIVATEKEY;
                $aop->alipayrsaPublicKey= self::ALIPAYRSAPUBLICKEY;
                $aop->apiVersion = '1.0';
                $aop->signType = 'RSA2';
                $aop->postCharset='UTF-8';
                $aop->format='json';
                $request = new \AlipayTradeQueryRequest ();
                // $request->setNotifyUrl(self::NOTIFTURL);
                $request->setBizContent("{" .
                "\"out_trade_no\":\"".$OBJECT."\"," .
                "\"org_pid\":\"\"," .
                "      \"query_options\":[" .
                // "        \"trade_settle_info\"" .
                "      ]" .
                "  }");
                $result = $aop->execute ( $request); 
                $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
                $resultCode = $result->$responseNode->code;
                if(!empty($resultCode)&&$resultCode == 10000){
                    return json_encode($result);
                } else {
                    return json_encode($result);
                }
                };
            //https://opendocs.alipay.com/apis/api_1/alipay.trade.refund
            //对应 alipay.trade.refund(统一收单交易退款接口)
            /*  $OBJECT= [
                    'out_trade_no'  => 订单号,
                    'refund_amount' => 金额
                ] */
            $phonerefund             = function() use (&$OBJECT){
                if(empty($OBJECT['out_trade_no'])){
                    return '缺少单号：out_trade_no';
                }
                if(empty($OBJECT['refund_amount'])){
                    return '缺少金额:refund_amount';
                }
                if(!empty($OBJECT['out_request_no'])){
                    $out_request_no ="\"out_request_no\":\"".$OBJECT['out_request_no']."\",";//退款请求号，业务时需要db
                }else{
                    $out_request_no = "";
                }
                vendor('aop.AopClient');
                vendor('aop.request.AlipayTradeRefundRequest');
                $aop = new \AopClient ();
                $aop->gatewayUrl = self::GATEWAYURL;
                $aop->appId = self::APPID;
                $aop->rsaPrivateKey = self::RSAPRIVATEKEY;
                $aop->alipayrsaPublicKey= self::ALIPAYRSAPUBLICKEY;
                $aop->apiVersion = '1.0';
                $aop->signType = 'RSA2';
                $aop->postCharset='UTF-8';
                $aop->format='json';
                $request = new \AlipayTradeRefundRequest ();
                // $request->setNotifyUrl(self::NOTIFTURL);
                $request->setBizContent("{" .
                "\"out_trade_no\":\"".$OBJECT['out_trade_no']."\"," .
                "\"refund_amount\":".$OBJECT['refund_amount']."," .//退款金额*
                "\"refund_reason\":\"正常退款\"," .
                $out_request_no.
                "\"operator_id\":\"OP001\"," .
                "\"store_id\":\"NJ_S_001\"," .
                "\"terminal_id\":\"NJ_T_001\"" .
                "  }");
                $result = $aop->execute ( $request); 

                $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
                dump($result);
                $resultCode = $result->$responseNode->code;
                if(!empty($resultCode)&&$resultCode == 10000){
                    echo "成功";
                } else {
                    echo "失败";
                }
                };
            //https://opendocs.alipay.com/apis/api_1/alipay.trade.fastpay.refund.query
            //对应 alipay.trade.fastpay.refund.query(统一收单交易退款查询)
            /* $OBJECT = 订单号 */
            $refundmsg               = function() use (&$OBJECT){
                if(empty($OBJECT)){
                    return '缺少单号';
                }
                vendor('aop.AopClient');
                vendor('aop.request.AlipayTradeFastpayRefundQueryRequest');
                $aop = new \AopClient ();
                $aop->gatewayUrl = self::GATEWAYURL;
                $aop->appId = self::APPID;
                $aop->rsaPrivateKey = self::RSAPRIVATEKEY;
                $aop->alipayrsaPublicKey= self::ALIPAYRSAPUBLICKEY;
                $aop->apiVersion = '1.0';
                $aop->signType = 'RSA2';
                $aop->postCharset='UTF-8';
                $aop->format='json';
                $request = new \AlipayTradeFastpayRefundQueryRequest ();
                $request->setNotifyUrl(self::NOTIFTURL);
                $request->setBizContent("{" .
                "\"trade_no\":\"\"," . //支付宝交易号，和商户订单号不能同时为空   
                "\"out_trade_no\":\"".$OBJECT."\"," .//订单支付时传入的商户订单号,和支付宝交易号不能同时为空。 trade_no,out_trade_no如果同时存在优先取trade_no
                "\"out_request_no\":\"".$OBJECT."\"," .//请求退款接口时，传入的退款请求号，如果在退款请求时未传入，则该值为创建交易时的外部交易号
                "\"org_pid\":\"\"," .
                "      \"query_options\":[" .
                // "        \"refund_detail_item_list\"" .
                "      ]" .
                "  }");
                $result = $aop->execute ( $request); 

                $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
                $resultCode = $result->$responseNode->code;
                dump($result);
                if(!empty($resultCode)&&$resultCode == 10000){
                echo "成功";
                } else {
                echo "失败";
                }
                };
            //https://opendocs.alipay.com/apis/api_1/alipay.trade.close
            //对应 alipay.trade.close(统一收单交易关闭接口)
            /* $OBJECT = 订单号 */
            $closepay                = function() use (&$OBJECT){
                if(empty($OBJECT)){
                    return '缺少单号';
                }
                vendor('aop.AopClient');
                vendor('aop.request.AlipayTradeCloseRequest');
                $aop = new \AopClient ();
                $aop->gatewayUrl = self::GATEWAYURL;
                $aop->appId = self::APPID;
                $aop->rsaPrivateKey = self::RSAPRIVATEKEY;
                $aop->alipayrsaPublicKey= self::ALIPAYRSAPUBLICKEY;
                $aop->apiVersion = '1.0';
                $aop->signType = 'RSA2';
                $aop->postCharset='UTF-8';
                $aop->format='json';
                $request = new \AlipayTradeCloseRequest ();
                $request->setBizContent("{" .
                "\"trade_no\":\"\"," .
                "\"out_trade_no\":\"".$OBJECT."\"," .
                "\"operator_id\":\"\"" .
                "  }");
                $result = $aop->execute ( $request); 
                $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
                $resultCode = $result->$responseNode->code;
                if(!empty($resultCode)&&$resultCode == 10000){
                echo "成功";
                } else {
                echo "失败";
                }
                };
            //https://opendocs.alipay.com/apis/api_15/alipay.data.dataservice.bill.downloadurl.query
            //对应 alipay.data.dataservice.bill.downloadurl.query(查询对账单下载地址)
            /*  $OBJECT= [
                    'bill_type'  => trade/signcustomer,trade指商户基于支付宝交易收单的业务账单；signcustomer是指基于商户支付宝余额收入及支出等资金变动的帐务账单
                    'bill_date'  => yyyy-MM-dd/yyyy-MM
                ] */    
            $AccountStatement        = function() use (&$OBJECT){
                if(empty($OBJECT['bill_type'])){
                    $OBJECT['bill_type']='signcustomer';
                }
                if(empty($OBJECT['bill_date'])){
                    $OBJECT['bill_date']='2020-04-17';
                }
                vendor('aop.AopClient');
                vendor('aop.request.AlipayDataDataserviceBillDownloadurlQueryRequest');
                $aop = new \AopClient ();
                $aop->gatewayUrl = self::GATEWAYURL;
                $aop->appId = self::APPID;
                $aop->rsaPrivateKey = self::RSAPRIVATEKEY;
                $aop->alipayrsaPublicKey= self::ALIPAYRSAPUBLICKEY;
                $aop->apiVersion = '1.0';
                $aop->signType = 'RSA2';
                $aop->postCharset='UTF-8';
                $aop->format='json';
                $request = new \AlipayDataDataserviceBillDownloadurlQueryRequest ();
                $request->setBizContent("{" .
                "\"bill_type\":\"".$OBJECT['bill_type']."\"," .
                "\"bill_date\":\"".$OBJECT['bill_date']."\"" .
                "  }");
                $result = $aop->execute ( $request); 
                $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
                $resultCode = $result->$responseNode->code;
                dump($result);
                if(!empty($resultCode)&&$resultCode == 10000){
                echo "成功";
                } else {
                echo "失败";
                }
                };
            //配置方法
            $engine                  = function ($ENGINE_type=null,$ENGINE_OBJECT=null) use (&$OBJECT,&$type,&$engine){
                // 默认入口
                $default_entrance   = "demo";
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
                    "phonepay"          => "支付",
                    "getpaymsg"         => "支付查询",
                    "phonerefund"       => "退款",
                    "refundmsg"         => "退款查询",
                    "closepay"          => "关闭订单",
                    "AccountStatement"  => "财务对账",
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
    

