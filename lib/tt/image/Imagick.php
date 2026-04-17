<?php
namespace tt\image;

class Imagick{
	const ORIENTATION_PORTRAIT = 1;
	const ORIENTATION_LANDSCAPE = 2;
	const ORIENTATION_SQUARE = 3;

	const CHANNELS_GRAY = 1;
	const CHANNELS_RGB = 3;
	const CHANNELS_CMYK = 4;	
	
	private static $font_path = [];
	private \Imagick $image;
	private int $dpi = 96;
	
	public function __construct(string $filename){
		if($filename != __FILE__){
			$this->image = new \Imagick($filename);
		}
	}

	public function __destruct(){
		$this->image->clear();
	}

	/**
	 * ライブラリのリソース制限を変更します
	 */
	public function set_resource_limit(int $resource_type, string $value){
		$this->image->setResourceLimit($resource_type, $value);
	}

	/**
	 * バイナリ文字列から画像を読み込む
	 */
	public static function read(string $data): self{
		$self = new static(__FILE__);
		$self->image = new \Imagick();
		
		if($self->image->readImageBlob($data) !== true){
			throw new \tt\image\exception\ImageException('invalid image');
		}
		return $self;
	}
	
	/**
	 * 塗りつぶした矩形を作成する
	 */
	public static function create(int $width, int $height, ?string $color=null): self{
		$self = new static(__FILE__);
		$self->image = new \Imagick();
		
		if(empty($color)){
			$color = '#FFFFFF';
		}

		try{
			$self->image->newImage($width,$height,$color);
		}catch (\ImagickException $e){
			throw new \tt\image\exception\ImageException();
		}
		return $self;
	}
	
	public function image(): \Imagick{
		return $this->image;
	}

	/**
	 * dpiを設定する
	 */
	public function set_dpi(int $dpi): void{
		$this->dpi = $dpi;
		$this->image->setResolution($this->dpi, $this->dpi);
	}

	/**
	 * ファイルに書き出す
	 * format: png, gif, jpeg
	 */
	public function write(string $filename, string $format=''): void{
		if(!empty($format)){
			$this->image->setImageFormat($format);
		}
		if(!is_dir(dirname($filename))){
			mkdir(dirname($filename), 0777, true);
		}
		$this->image->writeImage($filename);
	}
	
	/**
	 * 画像を出力する
	 * format: png, gif, jpeg
	 */
	public function output(string $format='jpeg'): void{
		$format = strtolower($format);
		
		switch($format){
			case 'png':
				header('Content-Type: image/png');
				break;
			case 'gif':
				header('Content-Type: image/gif');
				break;
			default:
				header('Content-Type: image/jpeg');
				$format = 'jpeg';
		}
		$this->image->setImageFormat($format);
		print($this->image);
	}
	
	/**
	 * 画像を返す
	 * format: png, gif, jpeg
	 */
	public function get(string $format='jpeg'): string{
		$format = strtolower($format);
		
		switch($format){
			case 'png':
				header('Content-Type: image/png');
				break;
			case 'gif':
				header('Content-Type: image/gif');
				break;
			default:
				header('Content-Type: image/jpeg');
				$format = 'jpeg';
		}
		$this->image->setImageFormat($format);
		return $this->image->getImageBlob();
	}
	
	
	/**
	 * 画像の一部を抽出する
	 */
	public function crop(int $width, int $height, ?int $x=null, ?int $y=null): self{
		[$w, $h] = $this->get_size();
		
		if($width >= $w && $height >= $h){
			return $this;
		}
		
		if($x === null || $y === null){
			$x = ceil(($w - $width) / 2);
			$y = ceil(($h - $height) / 2);
			
			[$x, $y] = [($x >= 0) ? $x : 0,($y >= 0) ? $y : 0];
		}
		if($x < 0){
			$x = $w + $x;
		}
		if($y < 0){
			$y = $h + $y;
		}
		$this->image->cropImage($width, $height, $x, $y);
		
		return $this;
	}
	
	/**
	 * 画像のサイズを変更する
	 */
	public function resize(?int $width, ?int $height=null, bool $fit=true): self{
		if(!empty($width) && !empty($height) && $fit === false){
			$this->image->scaleImage($width, $height, false);
		}else{
			[$w, $h] = $this->get_size();
			$rw = empty($width) ? 1 : $width;
			$rh = empty($height) ? 1 : $height;
			
			if(!empty($width) && !empty($height)){
				$aw = $rw / $w;
				$ah = $rh / $h;
				$a = max($aw,$ah);
			}else if(!isset($height)){
				$a = $rw / $w;
			}else{
				$a = $rh / $h;
			}
			$cw = ceil($w * $a);
			$ch = ceil($h * $a);
			
			$this->image->scaleImage((int)$cw, (int)$ch);
		}
		
		return $this;
	}
	
	/**
	 * 指定した幅と高さに合うようにリサイズとトリミングをする
	 */
	public function crop_resize(int $width, int $height): self{
		$this->resize($width,$height)->crop($width, $height);
		
		return $this;
	}
	
	/**
	 * 回転
	 */
	public function rotate(int $angle, string $background_color='#000000'): self{
		$this->image->rotateImage($background_color, $angle);

		return $this;
	}
	
	/**
	 * マージ
	 * @see https://www.php.net/manual/ja/imagick.constants.php
	 */
	public function merge(int $x, int $y, self $imagick, int $composite=\Imagick::COMPOSITE_OVER): self{
		$this->image->compositeImage(
			$imagick->image,
			$composite,
			$x,
			$y
		);
		return $this;
	}
	
	
	/**
	 * サイズ(w, h)
	 */
	public function get_size(): array{
		$w = $this->image->getImageWidth();
		$h = $this->image->getImageHeight();
		
		return [$w, $h];
	}
	
	/**
	 * 画像の向き
	 */
	public function get_orientation(): int{
		[$w, $h] = $this->get_size();
		
		$d = $h / $w;
		
		if($d <= 1.02 && $d >= 0.98){
			return self::ORIENTATION_SQUARE;
		}else if($d > 1){
			return self::ORIENTATION_PORTRAIT;
		}else{
			return self::ORIENTATION_LANDSCAPE;
		}
	}
	
	/**
	 * オプションを設定する
	 * @param mixed $v
	 * @see https://www.php.net/manual/ja/imagick.setoption.php
	 */
	public function set_option(string $k, $v): self{
		$this->image->setOption($k,$v);
		return $this;
	}
	
	/**
	 * 差分の抽出
	 */
	public function diff(self $imagick): self{
		$result = $this->image->compareImages($imagick->image, \Imagick::METRIC_MEANSQUAREERROR);
		
		$diff = new static(__FILE__);
		$diff->image = $result[0];
		
		return $diff;
	}
	
	/**
	 * 点を描画する
	 * xys: [[x,y]]の２次元配列
	 */
	public function point(array $xys, string $color): self{
		$draw = new \ImagickDraw();
		$draw->setFillColor(new \ImagickPixel($color));
		
		foreach($xys as $xy){
			$draw->point($xy[0], $xy[1]);
		}
		$this->image->drawImage($draw);
		
		return $this;
	}
	
	/**
	 * 矩形を描画する
	 * thickness: 線の太さ (塗り潰し時無効)
	 * fill: 塗りつぶす
	 * alpha: 0〜127 (透明) PNGでのみ有効
	 */
	public function rectangle(int $x, int $y, int $width, int $height, string $color, float $thickness=1, bool $fill=false, int $alpha=0): self{
		$draw = $this->get_draw($color, $thickness, $fill, $alpha);
		$draw->rectangle($x, $y, $x + $width, $y + $height);
		$this->image->drawImage($draw);
		
		return $this;
	}
	/**
	 * 線を描画
	 * thickness: 線の太さ
	 * alpha: 0〜127 (透明) PNGでのみ有効
	 */
	public function line(int $sx, int $sy, int $ex, int $ey, string $color, float $thickness=1, int $alpha=0): self{
		$draw = $this->get_draw($color, $thickness, false, $alpha);
		$draw->line($sx, $sy, $ex, $ey);
		$this->image->drawImage($draw);
		
		return $this;
	}
	
	/**
	 * 楕円を描画する
	 * thickness: 線の太さ (塗り潰し時無効)
	 * fill: 塗りつぶす
	 * alpha: 0〜127 (透明) PNGでのみ有効
	 */
	public function ellipse(int $cx, int $cy, int $width, int $height, string $color, float $thickness=1, bool $fill=false, int $alpha=0): self{
		$draw = $this->get_draw($color, $thickness, $fill, $alpha);
		$draw->ellipse($cx, $cy, $width/2, $height/2, 0, 360);
		$this->image->drawImage($draw);
		
		return $this;
	}
	
	private function get_draw(string $color, float $thickness=1, bool $fill=false, int $alpha=0): \ImagickDraw{
		$draw = new \ImagickDraw();
		
		if($fill){
			$draw->setFillColor(new \ImagickPixel($color));
			
			if($alpha > 0){
				$draw->setFillOpacity(round($alpha/127,3));
			}
		}else{
			$draw->setFillOpacity(0);
			
			if($thickness > 0){
				$draw->setStrokeColor(new \ImagickPixel($color));
				$draw->setStrokeWidth($thickness);
				
				if($alpha > 0){
					$draw->setStrokeOpacity(round($alpha/127,3));
				}
			}
		}
		return $draw;
	}
	
	/**
	 * フォントファイル(ttf)パスに名前を設定する
	 */
	public static function set_font(string $font_path, ?string $font_name=null): void{
		if(empty($font_name)){
			$font_name = preg_replace('/^(.+)\..+$/','\\1',basename($font_path));
		}
		if(!is_file($font_path)){
			throw new \tt\image\exception\AccessDeniedException($font_name.' access denied');
		}
		self::$font_path[$font_name] = $font_path;
	}
	
	/**
	 * テキストを画像に書き込む
	 */
	public function text(int $x, int $y, string $font_color, float $font_point_size, string $font_name, string $text, array $opt=[]): self{
		$values = explode("\n", str_replace("\r\n", "\n", $text));

		$draw = $this->get_text_draw($font_point_size, $font_name);
		$draw->setFillColor(new \ImagickPixel($font_color));

		if(isset($opt['stroke_color'])){
			$draw->setStrokeWidth($opt['stroke_width'] ?? 0.1);
			$draw->setStrokeColor(new \ImagickPixel($opt['stroke_color']));
		}

		$rotate = $opt['rotate'] ?? 0;
		if($rotate !== 0){
			$draw->rotate($rotate);
		}

		$tracking = \tt\image\Unit::pt2px($opt['tracking'] ?? 0, $this->dpi);
		if($tracking !== 0){
			$draw->setTextKerning($tracking);
		}
		$leading = \tt\image\Unit::pt2px($opt['leading'] ?? 0, $this->dpi);

		if($leading === 0){
			$text_metrics = $this->image->queryFontMetrics($draw, $text);
			$text_height = $text_metrics['textHeight'] * ($this->dpi / 96);
		}else{
			$text_height = $leading * sizeof($values);
		}

		$box_width = $opt['width'] ?? 0;
		$box_height = $opt['height'] ?? 0;
		$box_align = $opt['align'] ?? 0;
		$box_valign = $opt['valign'] ?? 0;

		if($box_height !== 0){
			if($box_valign === 1){
				$y = $y + ($box_height - $text_height) / 2;
			}else if($box_valign === 2){
				$y = $y + $box_height - $text_height;
			}
		}

		foreach($values as $value){
			$value_height = ($leading > 0) ? $leading : $this->image->queryFontMetrics($draw, $value)['textHeight'] * ($this->dpi / 96);
			$vx = $x;
			$vy = $y + $value_height;

			if($box_align !== 0 && $box_width !== 0){
				if($box_align === 1){
					$vx = $vx + ($box_width / 2);
					$draw->setTextAlignment(\Imagick::ALIGN_CENTER);
				}else if($box_align === 2){
					$vx = $vx + $box_width;
					$draw->setTextAlignment(\Imagick::ALIGN_RIGHT);
				}
			}
			$draw->annotation($vx, $vy, $value);
			$y += $value_height;
		}
		$this->image->drawImage($draw);
		return $this;
	}

	/**
	 * テキストの幅と高さ
	 */
	public function get_text_size(float $font_point_size, string $font_name, string $text): array{
		$draw = $this->get_text_draw($font_point_size, $font_name);
		$metrics = $this->image->queryFontMetrics($draw, $text);
		$w = $metrics['textWidth'];
		$h = $metrics['textHeight'];
		
		return [$w, $h];
	}
	
	private function get_text_draw(float $font_point_size, string $font_name): \ImagickDraw{
		if(!isset(self::$font_path[$font_name])){
			throw new \tt\image\exception\UndefinedException('undefined font `'.$font_name.'`');
		}
		
		$draw = new \ImagickDraw();
		$draw->setFont(self::$font_path[$font_name]);
		$draw->setFontSize($font_point_size);
		
		return $draw;
	}

	/**
	 * 画像の情報
	 *  int width
	 *  int height
	 *  int orientation 画像の向き 1: PORTRAIT, 2: LANDSCAPE, 3: SQUARE
	 *  string mime 画像形式のMIMEタイプ
	 *  int bits
	 *  int channels 1: GRAY, 3: RGB, 4: CMYK
	 *  bool broken 画像ファイルとして破損しているか
	 *  
	 * @see http://jp2.php.net/manual/ja/function.getimagesize.php
	 * @see http://jp2.php.net/manual/ja/function.image-type-to-mime-type.php
	 */
	public static function get_info(string $filename): array{
		if(!is_file($filename)){
			throw new \tt\image\exception\AccessDeniedException($filename.' not found');
		}
		$info = getimagesize($filename);
		$mime = $info['mime'] ?? null;
		$broken = null;
		
		if($mime == 'image/jpeg'){
			$broken = (['ffd8','ffd9'] != self::check_file_type($filename, 2, 2));
		}else if($mime == 'image/png'){
			$broken = (['89504e470d0a1a0a','0000000049454e44ae426082'] != self::check_file_type($filename, 8, 12));
		}else if($mime == 'image/gif'){
			$broken = (['474946','3b'] != self::check_file_type($filename, 3, 1));
		}
		
		return [
			'width'=>$info[0],
			'height'=>$info[1],
			'orientation'=>self::judge_orientation($info[0], $info[1]),
			'mime'=>$mime,
			'bits'=>$info['bits'] ?? null,
			'channels'=>$info['channels'] ?? null,
			'broken'=>$broken,
		];
	}

	private static function check_file_type(string $filename, string $header, string $footer): array{
		$fp = fopen($filename, 'rb');
		$a = unpack('H*', fread($fp, $header));
		fseek($fp, $footer * -1, SEEK_END);
		$b = unpack('H*', fread($fp, $footer));
		fclose($fp);
		return [($a[1] ?? null),($b[1] ?? null)];
	}
	
	private static function judge_orientation(float $w, float $h): int{
		if($w > 0 && $h > 0){
			$d = $h / $w;
			
			if($d <= 1.02 && $d >= 0.98){
				return self::ORIENTATION_SQUARE;
			}else if($d > 1){
				return self::ORIENTATION_PORTRAIT;
			}
			return self::ORIENTATION_LANDSCAPE;
		}
		return 0;
	}
}