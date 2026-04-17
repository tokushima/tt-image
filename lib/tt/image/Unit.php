<?php
namespace tt\image;

class Unit{
	/**
	 * mm -> pixel
	 */
	public static function mm2px(float $mm, float $dpi=72): int{
		$x = ($mm / 25.4 * $dpi);
		return (int)ceil($x);
	}

	/**
	 * point -> pixel
	 */
	public static function pt2px(float $pt, float $dpi=72): int{
		$x = ($pt / 72 * $dpi);
		return (int)ceil($x);
	}

	/**
	 * point -> mm
	 */
	public static function pt2mm(float $pt): float{
		return ($pt * 0.352778);
	}

	/**
	 * pixel -> dpi
	 */
	public static function px2dpi(int $px, float $mm, int $precision=0): float{
		$dpi = ($px / $mm * 25.4);
		return (!empty($precision)) ? round($dpi, $precision) : $dpi;
	}
}
