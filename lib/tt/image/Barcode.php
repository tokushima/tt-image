<?php
namespace tt\image;

class Barcode{
	public $data = [];
	public $type = [];
	
	public $bar_height;
	public $module_width;
	
	public function __construct(array $data, array $type, float $bar_height, float $module_width){
		$this->data = $data;
		$this->type = $type;
		$this->bar_height = \tt\image\Calc::mm2px($bar_height);
		$this->module_width = \tt\image\Calc::mm2px($module_width);
	}
	
	/**
	 * NW-7 (CODABAR)
	 * A0123456789A
	 */
	public static function NW7(string $code): self{
		if(!preg_match('/^[0123456789ABCD\-\$:\/\.\+]+$/i',$code)){
			throw new \tt\image\exception\InvalidArgumentException('detected invalid characters');
		}
		if(!preg_match('/^[ABCD]/i',$code) || !preg_match('/[ABCD]$/i',$code)){
			throw new \tt\image\exception\InvalidArgumentException('Start / Stop code is not [A, B, C, D]');
		}
		$bits = [
			'0'=>[1,-1,1,-1,1,-3,3,-1],
			'1'=>[1,-1,1,-1,3,-3,1,-1],
			'2'=>[1,-1,1,-3,1,-1,3,-1],
			'3'=>[3,-3,1,-1,1,-1,1,-1],
			'4'=>[1,-1,3,-1,1,-3,1,-1],
			'5'=>[3,-1,1,-1,1,-3,1,-1],
			'6'=>[1,-3,1,-1,1,-1,3,-1],
			'7'=>[1,-3,1,-1,3,-1,1,-1],
			'8'=>[1,-3,3,-1,1,-1,1,-1],
			'9'=>[3,-1,1,-3,1,-1,1,-1],
			'-'=>[1,-1,1,-3,3,-1,1,-1],
			'$'=>[1,-1,3,-3,1,-1,1,-1],
			':'=>[3,-1,1,-1,3,-1,3,-1],
			'/'=>[3,-1,3,-1,1,-1,3,-1],
			'.'=>[3,-1,3,-1,3,-1,1,-1],
			'+'=>[1,-1,3,-1,3,-1,3,-1],
			'A'=>[1,-1,3,-3,1,-3,1,-1],
			'B'=>[1,-3,1,-3,1,-1,3,-1],
			'C'=>[1,-1,1,-3,1,-3,3,-1],
			'D'=>[1,-1,1,-3,3,-3,1,-1],
		];
		
		$fcode = strtoupper($code);
		$data = [-11]; // quietzone
		for($i=0;$i<strlen($fcode);$i++){
			$data = array_merge($data,$bits[$fcode[$i]]);
		}
		$data[] = -11; // quietzone
		return new static([$data], [], 10, 0.6);
	}
	
	/**
	 * EAN13 (JAN13) 
	 * 4549995186550
	 */
	public static function EAN13(string $code): self{
		$get_checkdigit_JAN = function($code){
			$odd = $even = 0;
			for($i=0;$i<12;$i+=2){
				$even += (int)$code[$i];
				$odd += (int)$code[$i+1];
			}
			$sum = (string)(($odd * 3) + $even);
			$digit1 = (int)$sum[strlen($sum)-1];
			$check = ($digit1 > 9 || $digit1 < 1) ? 0 : (10 - $digit1);
			return $check;
		};
		
		$get_data_JAN = function($code){
			$data = [[],[]];
			
			$parity_pattern = [ // 0:偶数 1:奇数
				'111111','110100','110010','110001','101100',
				'100110','100011','101010','101001','100101'
			];
			$pattern = $parity_pattern[$code[0]];
			$parity = [];
			
			$parity[0][0] = [ // 左 パリティ 偶数
				[-1,1,-2,3],[-1,2,-2,2],[-2,2,-1,2],[-1,1,-4,1], [-2,3,-1,1],
				[-1,3,-2,1],[-4,1,-1,1],[-2,1,-3,1], [-3,1,-2,1],[-2,1,-1,3]
			];
			$parity[0][1] = [ // 左 パリティ 奇数
				[-3,2,-1,1],[-2,2,-2,1], [-2,1,-2,2],[-1,4,-1,1],[-1,1,-3,2],
				[-1,2,-3,1],[-1,1,-1,4],[-1,3,-1,2],[-1,2,-1,3], [-3,1,-1,2]
			];
			$parity[1] = [ // 右 パリティ
				[3,-2,1,-1],[2,-2,2,-1],[2,-1,2,-2],[1,-4,1,-1],[1,-1,3,-2],
				[1,-2,3,-1],[1,-1,1,-4],[1,-3,1,-2],[1,-2,1,-3],[3,-1,1,-2]
			];
			
			foreach(str_split(substr($code,1)) as $k => $n){
				if($k < 6){
					$data[0][] = $parity[0][$pattern[$k]][$n];
				}else{
					$data[1][] = $parity[1][$n];
				}
			}
			
			return array_merge(
				[[-11]], // quietzone
				[[1,-1,1]],
				$data[0],
				[[-1,1,-1,1,-1]],
				$data[1],
				[[1,-1,1]],
				[[-7]] // quietzone
			);
		};
		$code = sprintf('%012d',$code);
		
		if(!ctype_digit($code)){
			throw new \tt\image\exception\InvalidArgumentException('detected invalid characters');
		}
		$code = (strlen($code) > 12) ? $code : $code.$get_checkdigit_JAN($code);
		return new static($get_data_JAN($code), [], 22.86, 0.33);
	}
	
	/**
	 * CODE39
	 * 1234567890ABCDEF
	 */	
	public static function CODE39(string $code): self{
		if(!preg_match('/^[\w\-\. \$\/\+%]+$/i',$code)){
			throw new \tt\image\exception\InvalidArgumentException('detected invalid characters');
		}
		
		$pattern = [
			'0'=>123,'1'=>334,'2'=>434,'3'=>531,'4'=>124,'5'=>321,'6'=>421,'7'=>135,'8'=>333,'9'=>433,
			'A'=>344,'B'=>444,'C'=>541,'D'=>164,'E'=>361,'F'=>461,'G'=>145,'H'=>343,'I'=>443,'J'=>163,'K'=>316,'L'=>416,'M'=>517,
			'N'=>176,'O'=>377,'P'=>477,'Q'=>118,'R'=>312,'S'=>412,'T'=>172,'U'=>214,'V'=>614,'W'=>811,'X'=>774,'Y'=>271,'Z'=>671,
			'-'=>715,'.'=>213,' '=>613,'*'=>773,'$'=>751,'/'=>737,'+'=>747,'%'=>157
		];
		$cahrbar = [null,111,221,211,112,212,122,121,222];
		
		$data = [];
		$data[] = -10; // quietzone
		
		$fcode = strtoupper('*'.$code.'*');
		for($i=0;$i<strlen($fcode);$i++){
			$ptn = (string)$pattern[$fcode[$i]];
			$bits = $cahrbar[$ptn[0]].$cahrbar[$ptn[1]].$cahrbar[$ptn[2]];
			
			for($c=0;$c<9;$c++){
				$data[] = $bits[$c] * (($c % 2 === 0) ? 1 : -1);
			}
			$data[] = -1; // gap
		}
		$data[] = -10; // quietzone
		return new static([$data], [], 10, 0.33);
	}
	
	/**
	 * 郵便カスタマーバーコード
	 * 1050011
	 * addressは４丁目２−８ （町域以降の住所）
	 * @see https://www.post.japanpost.jp/zipcode/zipmanual/index.html
	 */
	public static function CustomerBarcode(string $zip, string $address=''): self{
		$data = $type = [];
		// CC1=!, CC2=#, CC3=%, CC4=@, CC5=(, CC6=), CC7=[, CC8=]
		
		$bits = [
			'0'=>[1,4,4],'1'=>[1,1,4],'2'=>[1,3,2],'3'=>[3,1,2],'4'=>[1,2,3],
			'5'=>[1,4,1],'6'=>[3,2,1],'7'=>[2,1,3],'8'=>[2,3,1],'9'=>[4,1,1],
			'!'=>[3,2,4],'#'=>[3,4,2],'%'=>[2,3,4],'@'=>[4,3,2],'('=>[2,4,3],
			')'=>[4,2,3],'['=>[4,4,1],']'=>[1,1,1],'-'=>[4,1,4],
		];
		$alphabits = [
			'A'=>'!0','B'=>'!1','C'=>'!2','D'=>'!3','E'=>'!4','F'=>'!5','G'=>'!6','H'=>'!7','I'=>'!8','J'=>'!9',
			'K'=>'#0','L'=>'#1','M'=>'#2','N'=>'#3','O'=>'#4','P'=>'#5','Q'=>'#6','R'=>'#7','S'=>'#8','T'=>'#9',
			'U'=>'%0','V'=>'%1','W'=>'%2','X'=>'%3','Y'=>'%4','Z'=>'%5',
		];
		$cdbits = [
			'0'=>0,'1'=>1,'2'=>2,'3'=>3,'4'=>4,'5'=>5,'6'=>6,'7'=>7,'8'=>8,'9'=>9,
			'-'=>10,'!'=>11,'#'=>12,'%'=>13,'@'=>14,'('=>15,')'=>16,'['=>17,']'=>18,
		];
		
		$zip = mb_convert_kana($zip,'a');
		$zip = str_replace('-','',$zip);
		
		if(!ctype_digit($zip)){
			throw new \tt\image\exception\InvalidArgumentException('detected invalid characters');
		}
		if(!empty($address)){
			$address = mb_convert_kana($address,'as');
			$address = mb_strtoupper($address);
			$address = preg_replace('/[&\/・.]/u','',$address);
			$address = preg_replace('/[A-Z]{2,}/u','-',$address);
			
			$m = [];
			if(preg_match_all('/([一二三四五六七八九十]+)(丁目|丁|番地|番|号|地割|線|の|ノ)/u',$address,$m)){
				foreach($m[0] as $k => $v){
					$v = preg_replace('/([一二三四五六七八九]+)十([一二三四五六七八九])/u','${1}${2}',$v);
					$v = preg_replace('/([一二三四五六七八九]+)十/u','${1}0',$v);
					$v = preg_replace('/十([一二三四五六七八九]+)/u','1${1}',$v);
					
					$address = str_replace($m[0][$k],str_replace(['一','二','三','四','五','六','七','八','九','十'],[1,2,3,4,5,6,7,8,9,10],$v),$address);
				}
			}
			$address = preg_replace('/[^\w-]/','-',$address);
			
			$address = preg_replace('/(\d)F$/','$1',$address);
			$address = preg_replace('/(\d)F/','$1-',$address);
			$address = preg_replace('/[-]+/','-',$address);
			$address = preg_replace('/-([A-Z]+)/','$1',$address);
			$address = preg_replace('/([A-Z]+)-/','$1',$address);
			
			if($address[0] === '-'){
				$address = substr($address,1);
			}
			if(substr($address,-1) === '-'){
				$address = substr($address,0,-1);
			}
		}
		
		$chardata = '';
		$str = $zip.$address;
		for($i=0;$i<strlen($str);$i++){
			$chardata .= ctype_alpha($str[$i]) ? $alphabits[$str[$i]] : $str[$i];
		}
		for($i=strlen($chardata);$i<20;$i++){
			$chardata .= '@';
		}
		$chardata = substr($chardata,0,20);
		
		// start
		array_push($data,-1,-1,-1,1,-1,1);
		array_push($type,0,0,0,1,0,3);
		
		$cdsum = 0;
		for($i=0;$i<strlen($chardata);$i++){
			foreach($bits[$chardata[$i]] as $t){
				array_push($data,-1,1);
				array_push($type,0,$t);
			}
			$cdsum += $cdbits[$chardata[$i]];
		}
		
		// ( N + sum ) % 19 === 0
		$cd = array_search(($cdsum % 19 === 0) ? 0 : 19 - ($cdsum % 19),$cdbits);
		
		// check digit
		foreach($bits[$cd] as $t){
			array_push($data,-1,1);
			array_push($type,0,$t);
		}
		
		// end
		array_push($data,-1,1,-1,1,-1,-1);
		array_push($type,0,3,0,1,0,0);
		
		return new static([$data], [$type], 3.6, 0.6);
	}
	
	public function bar_type(float $i, float $j): array{
		if(!empty($this->type)){
			$div_bar = $this->bar_height / 3;

			switch($this->type[$i][$j] ?? 1){
				case 1: // ロングバー
					return [0, $this->bar_height];
				case 2: // セミロングバー（上）
					return [0, $div_bar * 2];
				case 3: // セミロングバー（下）
					return [$div_bar, $this->bar_height];
				case 4: // タイミングバー
					return [$div_bar, $div_bar * 2];
				default:
			}
		}
		return [0, $this->bar_height];
	}

	/**
	 * 画像を出力
	 * 
	 * opt:
	 * 	string $color #000000
	 *  string $bgcolor #FFFFFF
	 * 	float $bar_height バーコードの高さ
	 * 	float $module_width 1モジュールの幅
	 */
	public function write(string $filename, array $opt=[]): string{
		if(isset($opt['bar_height'])){
			$this->bar_height = $opt['bar_height'];
		}
		
		$bgcolor = $opt['bgcolor'] ?? null;
		$color = $opt['color'] ?? '000000';

		$color2rgb = function($color_code){
			if(substr($color_code,0,1) == '#'){
				$color_code = substr($color_code,1);
			}	
			$r = hexdec(substr($color_code,0,2));
			$g = hexdec(substr($color_code,2,2));
			$b = hexdec(substr($color_code,4,2));
			return [$r, $g, $b];
		};

		$w = 0;
		foreach($this->data as $d){
			foreach($d as $bw){
				$w += ($bw < 0) ? ($bw * -1) : $bw;
			}
		}
		
		$canvas = imagecreatetruecolor($w * $this->module_width, $this->bar_height);
		$alpha = 0;
		
		if(empty($bgcolor)){
			$bgcolor = 'FFFFFF';
			imagealphablending($canvas, false);
			imagesavealpha($canvas, true);
			$alpha = 127;
		}
		list($r,$g,$b) = $color2rgb($bgcolor);
		imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, $r, $g, $b, $alpha));
		
		list($r,$g,$b) = $color2rgb($color);
		$c = imagecolorallocate($canvas, $r, $g, $b);
		imagesetthickness($canvas, 1);
		
		$x = 0;
		foreach($this->data as $i => $d){
			foreach($d as $j => $bw){
				if($bw < 0){
					$x += ($bw * -1) * $this->module_width;
				}else{
					list($sy,$ey) = $this->bar_type($i,$j);
					
					for($k=0;$k<$bw * $this->module_width;$k++){
						$x++;
						
						imageline($canvas,$x, $sy, $x, $ey, $c);
					}
				}
			}
		}
		imagepng($canvas, $filename);
		imagedestroy($canvas);
		
		return $filename;
	}
}


