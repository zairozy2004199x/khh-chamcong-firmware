<?php
/**
 * VCG_Nhap — ghi kết quả đọc CSV xuống bảng.
 *
 * TÁCH RIÊNG KHỎI `VCG_Nap` vì hai việc khác hẳn nhau: bên kia là ĐỌC và chuẩn hoá (thuần, thử
 * được bằng con số), bên này là GHI (đụng cơ sở dữ liệu, không thử được nếu không có máy chủ).
 * Trộn hai thứ lại là phần lô-gic quan trọng nhất bị khoá sau một kết nối MySQL, và rồi không
 * ai thử nó nữa.
 *
 * @package vhcp-cong
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VCG_Nhap {

	/**
	 * Ghi hồ sơ người + bảng gán cơ sở.
	 *
	 * KHÔNG XOÁ TRẮNG RỒI GHI LẠI. Nạp lại một tệp cũ hơn mà xoá trắng trước là mất những người
	 * chỉ có trong tệp mới hơn — và mất im lặng. Chỉ thêm hoặc cập nhật.
	 *
	 * @return array{nguoi_them:int,nguoi_sua:int,gan_them:int}
	 */
	public static function ghi_nhan_vien( $kq ) {
		global $wpdb;
		$b_nv = VCG_DB::bang( 'nv' );
		$b_dv = VCG_DB::bang( 'nv_donvi' );
		$them = 0; $sua = 0; $gan = 0;

		foreach ( $kq['nguoi'] as $p ) {
			$co = $wpdb->get_var( $wpdb->prepare( "SELECT ma_nv FROM $b_nv WHERE ma_nv=%s", $p['ma_nv'] ) );
			$p['sua_luc'] = current_time( 'mysql' );
			if ( null === $co ) {
				$wpdb->insert( $b_nv, $p );
				$them++;
			} else {
				$wpdb->update( $b_nv, $p, array( 'ma_nv' => $p['ma_nv'] ) );
				$sua++;
			}
		}
		foreach ( $kq['gan'] as $g ) {
			/* Khoá duy nhất là cặp (ma_nv, don_vi) nên INSERT IGNORE là đủ: nạp lại cùng tệp
			   không đẻ thêm dòng, mà cũng không xoá cặp nào đang có. */
			$sql = $wpdb->prepare(
				"INSERT IGNORE INTO $b_dv (ma_nv, don_vi, tinh_thanh, tao_luc) VALUES (%s,%s,%s,%s)",
				$g['ma_nv'], $g['don_vi'], $g['tinh_thanh'], $g['tao_luc']
			);
			$gan += (int) $wpdb->query( $sql );
		}
		return array( 'nguoi_them' => $them, 'nguoi_sua' => $sua, 'gan_them' => $gan );
	}

	/**
	 * Ghi lượt chấm công, ÁP LUẬT NỚI RỘNG.
	 *
	 * 🔴 Đọc dòng cũ rồi gộp, chứ KHÔNG ghi đè thẳng. Ghi đè thẳng là nạp một tệp chỉ có nửa
	 *    ngày sẽ cắt mất giờ ra đã có — ăn mất công của người ta, và không có gì báo.
	 *
	 * @return array{them:int,noi:int,giu:int}
	 */
	public static function ghi_cong( $luot ) {
		global $wpdb;
		$b = VCG_DB::bang( 'ngay' );
		$them = 0; $noi = 0; $giu = 0;

		foreach ( $luot as $x ) {
			$cu = $wpdb->get_row( $wpdb->prepare(
				"SELECT id, vao, ra, anh_vao, anh_ra FROM $b WHERE co_so=%s AND ngay=%s AND ma_nv=%s",
				$x['co_so'], $x['ngay'], $x['ma_nv']
			), ARRAY_A );

			if ( null === $cu ) {
				$wpdb->insert( $b, array(
					'co_so'   => $x['co_so'],
					'ngay'    => $x['ngay'],
					'ma_nv'   => $x['ma_nv'],
					'ho_ten'  => $x['ho_ten'],
					'vao'     => $x['vao'],
					'ra'      => $x['ra'],
					'anh_vao' => $x['anh_vao'],
					'anh_ra'  => $x['anh_ra'],
					'nguon'   => 'csv',
					'sua_luc' => current_time( 'mysql' ),
				) );
				$them++;
				continue;
			}

			$k = VCG_DB::gop_gio(
				( null === $cu['vao'] ) ? null : (int) $cu['vao'],
				( null === $cu['ra'] ) ? null : (int) $cu['ra'],
				$x['vao'], $x['ra']
			);
			if ( ! $k['doi'] ) { $giu++; continue; }

			$wpdb->update( $b, array(
				'vao'     => $k['vao'],
				'ra'      => $k['ra'],
				/* Ảnh: chỉ ĐIỀN VÀO CHỖ TRỐNG, không đè ảnh đã có. Ảnh cũ là bằng chứng của
				   lượt bấm cũ; đè nó là mất bằng chứng mà bảng công vẫn trông bình thường. */
				'anh_vao' => ( '' !== $cu['anh_vao'] && null !== $cu['anh_vao'] ) ? $cu['anh_vao'] : $x['anh_vao'],
				'anh_ra'  => ( '' !== $cu['anh_ra'] && null !== $cu['anh_ra'] ) ? $cu['anh_ra'] : $x['anh_ra'],
				'sua_luc' => current_time( 'mysql' ),
			), array( 'id' => (int) $cu['id'] ) );
			$noi++;
		}
		return array( 'them' => $them, 'noi' => $noi, 'giu' => $giu );
	}
}
