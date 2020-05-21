<?php
namespace app\server;
class ListAlgorithm{
//$type方法名 variable1/variable2要对比数据 $OBJECT具体传递参数(目前仅交集可用)
 static public function ListAlgorithm($type,$variable1,$variable2,$OBJECT=null){
	    //差集
	    $Except				= function () use ($variable1,$variable2){
	    	foreach ($variable1 as $key1 => $value1) {
	    		if(isset($variable2[$key1])){
	    			unset($variable2[$key1]);
	    			unset($variable1[$key1]);
	    		}
	    	}
	    	return array_merge($variable1,$variable2);
	    	};
	    //交集 $OBJECT=>ture 时，覆盖模式:$variable1 > $variable2 覆盖模式关键词cover默认false
	    /**
	    $OBJECT = [
			"cover"	=>	true/false 不填会自动填
			"type"	=>	1	//数组都是默认排序使用
	    ]
	    */
	    $Integration 		= function () use ($variable1,$variable2,$OBJECT){
	    	//初始化
	    	if(empty($OBJECT['cover'])){
	    		$OBJECT['cover']=false;
	    	};
	    	if(isset($OBJECT['type'])){
	    		switch ($OBJECT['type']) {
	    			case '1':
	    				foreach ($variable1 as $key => $value) {
	    					$variable1[$value]=$value;
	    					unset($variable1[$key]);
	    					# code...
	    				}
	    				foreach ($variable2 as $key => $value) {
	    					$variable2[$value]=$value;
	    					unset($variable2[$key]);
	    					# code...
	    				}
	    				break;
	    		}
	    	}
	    	$thisReturn;
	    	foreach ($variable1 as $key1 => $value1) {
	    		foreach ($variable2 as $key2 => $value2) {
	    			if($key1==$key2){
	    				if(!$OBJECT['cover']){
		    				if($value1==$value2){
		    					$thisReturn[$key1]=$value1;
		    				}
	    				}else{
	    					$thisReturn[$key1]=$value1;
	    				}
	    				break;
	    			}
	    		}
	    	}
	    	return isset($thisReturn)?$thisReturn:[];
	    	};
	    //方法算法原因，要配合辅助函数进入
	    //并集	$value0 > $returnValue =>key同时存在时选用value0的value
	    $Union 				= function ($value0,$returnValue=null,$depth="",$depthnumber=1,$lastdepthnumber=0) use (&$Union){
	    	$UnionDoctor=function  ($Original,$key,$value) use(&$UnionDoctor){
	        	try {
	                eval("\$Original".$key."='".$value."';");
	                $returnkey=$key;
	            } catch (\Exception $e) {
	            	$key=substr($key,0,strripos($key,"["));
	                $returnkey=  $UnionDoctor($Original,$key,$value);
	            }
	            return $returnkey;
	    	};
	      	foreach ($value0 as $key1 => $value1) {
	        	if($depthnumber>$lastdepthnumber){
		          $lastdepthnumber=$depthnumber;
		          $depth=$depth."['".$key1."']";
	        	}else{
		          $depth=substr($depth,0,strripos($depth,"["));
		          $depth.="['".$key1."']";
		        }
		        if(is_array($value1)){
		          $depthnumber++;
		          $returnValue=$Union($value1,$returnValue,$depth,$depthnumber);
		          $depth=substr($depth,0,strripos($depth,"["));
		          $lastdepthnumber--;
		          $depthnumber--;
		        }else{
		          try {
						eval("\$returnValue".$depth."='".$value1."';");      		
		            } catch (\Exception $e) {
		                $returnkey= $UnionDoctor($returnValue,$depth,$value1);
		           		eval("\$returnValue".$returnkey."=null;");
						eval("\$returnValue".$depth."='".$value1."';");
		            }
		        }
		     }
	      	return $returnValue;
	  		};
	  	//并集辅助方法
	  	$Auxiliary_Union 	= function () use ($Union,$variable1,$variable2){
	  		return $Union($variable1,$variable2);
	    	};
	    //别名入口
    	//仅支持一维数组，别名为值，值必须小写
	    $Default 			= function(){
    		return [
    			"Integration"		=>	["交","交集","i","integration"],
    			"Except"			=>	["差","差集","e","except"],
    			"Auxiliary_Union"	=>	["并","并集","u","union"],
    		];
    		};
    	//别名支持方法
		$SeekFormDefault	= function() use ($type,$Default){
    		if(isset($Default)){
	    		$FindValue=strtolower($type);
	    		$return=array_search($FindValue,$Default());
	    		if(is_bool($return)){
		    		foreach($Default() as $key => $value){
		    			if(is_array($value)){
		    				$return=array_search($FindValue,$value);
		    			}
		    			if(!is_bool($return)){
		    				$join=$key;
		    			}
		    		}
	    		}else{
					$join=$return;
				}
				return isset($join)?$join:null;
			}
			return null;
    		};
    		// 唯一入口
    	return isset($SeekFormDefault)&&!empty($SeekFormDefault()) ? ${$SeekFormDefault()}() :  (isset(${$type}) ? ${$type}() : null);
  	}

}


