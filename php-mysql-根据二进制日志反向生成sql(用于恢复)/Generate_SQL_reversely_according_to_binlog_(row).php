<?php

//开发框架为phalapi 其他框架换成对应的db类
/*
	本文件为反向生成日志sql的工具
*/

    public function index() {

    	//日志文件的内容
		$str = "";

		//开始与结束
		// preg_match_all('/# at 4922.*/s', $str,$return0);
		// preg_match_all('/###(.*)\n/', $return0[0][0],$return);

		//所有sql执行
		preg_match_all('/###(.*)\n/', $str,$return);
		$database=[];
		$i = -1;
		$i2 = 0;
		$arr = [];
		echo "<pre>";
		foreach ($return[1] as $key => $value) {
		    preg_match_all('/(DELETE)|(UPDATE)|(INSERT)/', $value,$tof);
		    if(!empty($tof[0])){
		        $i = $i+1;
		        $i2 = 0;
		    }

		    $arr[$i][$i2]=$value;

		    $i2=$i2+1;
		}
	    $sql = "";
	    foreach (array_reverse($arr) as $key => $value) {
	        preg_match_all('/(DELETE)|(UPDATE)|(INSERT)/', $value[0],$tof2);
	        switch ($tof2[0][0]) {
	            case 'DELETE':
	                $sqlarr=[];
	                preg_match_all('/FROM(.*)/', $value[0],$tbn);
	                foreach ($value as $key2 => $value2) {
	                    preg_match_all('/@[0-9]*?=(.*?)\/\*/', $value2,$tof3);
	                    if(!empty($tof3[1][0])){
	                        array_push($sqlarr, $tof3[1][0]);
	                    }
	                }
	                $sql = $sql."INSERT INTO ".$tbn[1][0]." VALUES (".implode(',',$sqlarr).");";
	                break;
	            case 'UPDATE':
	                preg_match_all('/UPDATE(.*)/', $value[0],$tbn);
	                if(empty($database[$tbn[1][0]])){
	                    $database[$tbn[1][0]]=$this->db($tbn[1][0]);
	                }
	                $update_i=0;
	                ${'sqlarr'.$update_i}=[];
	                foreach ($value as $key2 => $value2) {
	                    preg_match_all('/@([0-9]*?)=(.*?)\/\*/', $value2,$tof3);
	                    if(preg_match('/SET/', $value2)){
	                        $update_i =1;
	                        ${'sqlarr'.$update_i}=[];
	                    }
	                    if(!empty($tof3[1][0])){
	                        array_push(${'sqlarr'.$update_i}, $database[$tbn[1][0]][$tof3[1][0]-1]['Field'].' = '.$tof3[2][0].' ');
	                    }
	                }
	                $sql = $sql."UPDATE ".$tbn[1][0]." SET ".implode(',',$sqlarr0)." WHERE ".implode(' AND ',$sqlarr1).';';
	                break;
	            case 'INSERT':
	                $sqlarr=[];
	                preg_match_all('/INTO(.*)/', $value[0],$tbn);
	                if(empty($database[$tbn[1][0]])){
	                    $database[$tbn[1][0]]=$this->db($tbn[1][0]);
	                }
	                foreach ($value as $key2 => $value2) {
	                    preg_match_all('/@([0-9]*?)=(.*?)\/\*/', $value2,$tof3);
	                    if(!empty($tof3[1][0])){
	                        // var_dump(expression)
	                        array_push($sqlarr, $database[$tbn[1][0]][$tof3[1][0]-1]['Field'].' = '.$tof3[2][0].' ');
	                    }
	                }
	                $sql = $sql."DELETE FROM  ".$tbn[1][0]." WHERE ".implode(' AND ',$sqlarr).';';
	                break;
	            
	            default:
	                echo 'error:'.$key;
	                var_dump(array_reverse($arr));
	                die;
	                break;
	        }
	    }
    }
    
    public function db($tablename)
    {
        return \PhalApi\DI()->notorm->diy->queryAll("desc ".$tablename);
    }